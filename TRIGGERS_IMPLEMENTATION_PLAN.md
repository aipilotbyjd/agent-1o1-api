# Trigger System — Implementation Plan

A complete, from-scratch specification for building the trigger subsystem in a new
Laravel application. This is not a refactor guide — every file described here is
created new, with the naming already settled.

**Target stack:** PHP 8.4, Laravel 12/13, Pest 4, a real queue driver (Redis +
Horizon recommended), MySQL or PostgreSQL in production.

---

## 1. What this system does

A **trigger** is the answer to "what makes this workflow or agent start?"

Four kinds of thing can start a run:

| Type | What starts it |
|---|---|
| `webhook` | An HTTP POST from an outside provider (GitHub, Stripe, Slack) |
| `schedule` | A cron expression matching the current minute |
| `polling` | A periodic HTTP fetch that finds new items |
| `manual` | A person clicking "Run" in the UI |

All four converge on one pipeline:

```
  something happens
        │
        ▼
  ┌───────────────────────────┐
  │ TriggerService::receive() │   fast: a few indexed queries + one INSERT
  │  · dedupe by delivery id  │   never calls a model, never walks a graph
  │  · apply config filters   │
  │  · check target can run   │
  │  · write trigger_events   │──── row is COMMITTED here
  └───────────┬───────────────┘
              │ then, and only then
              ▼
  ┌───────────────────────────┐
  │ FireTriggerEvent (queued)  │   slow: starts a workflow run, or blocks on
  │  · claim the event         │        an agent's model call
  │  · TriggerService::fire()  │
  │  · mark fired / finish     │
  └───────────┬───────────────┘
              │ if the job never comes back
              ▼
  ┌───────────────────────────┐
  │ triggers:retry-stuck       │   every 5 min: re-queue anything left
  │  (RetryStuckEventsCommand) │   `queued` or `running` past its grace period
  └───────────────────────────┘
```

### The one rule that makes it lossless

**The event row is committed before the job is dispatched.** If the dispatch
fails, the process dies, or the queue is flushed, the row is still sitting at
`queued` for the retry command to pick back up. Retries only help while a job
exists to be retried — this is what covers the case where no job exists at all.

Everything else in this document follows from that rule.

---

## 2. Prerequisites

The trigger system is not standalone. Before starting, the host application must
already provide these. If any are missing, build them first.

| Requirement | Used for |
|---|---|
| `Workspace` model + `workspace.context` middleware | Every trigger is scoped to a workspace |
| `User` model | `triggers.created_by` |
| `Run` model with a `status` enum and `nullableMorphs('runnable')` | What a fired trigger produces |
| `Workflow` model with a `status` of `published`/`draft` and soft deletes | A trigger target |
| `Agent` model with soft deletes | A trigger target |
| `WorkflowRunner::start(Workflow, ?User, array $input, string $source): Run` | Firing a workflow trigger |
| `AgentChatService::sendFromTrigger(Agent, array $input, string $source, ?string $template): Run` | Firing an agent trigger |
| `Credential` model with `type`, `data`, `isExpired()`, `touchLastUsed()` | Authenticating polling requests |
| `Permission` enum + `requirePermission()` on the base controller | API authorization |
| `ApiResponse` helper (`success`, `created`, `error`, `notFound`) | Consistent JSON envelopes |
| A queue driver that is **not** `sync` | The entire async design |

**Composer:** `dragonmantank/cron-expression` ships transitively with Laravel, but
require it explicitly — this system depends on it directly:

```bash
composer require dragonmantank/cron-expression
```

**Permissions to add** to the host app's `Permission` enum:

```php
case TriggerView       = 'trigger.view';
case TriggerManage     = 'trigger.manage';
case TriggerRun        = 'trigger.run';
case TriggerTokenRotate = 'trigger.token.rotate';
case TriggerEventView  = 'trigger-event.view';
```

Assign `TriggerView` / `TriggerEventView` to read-only roles, `TriggerRun` to
member roles, `TriggerManage` to admin, `TriggerTokenRotate` to owner.

---

## 3. Naming conventions

These were the hard-won decisions. Apply them consistently.

1. **The class name carries the noun; methods don't repeat it.** On
   `TriggerService` it is `fire()`, not `fireTrigger()`.
2. **Public methods read as the call site.** Private helpers are fully
   descriptive (`matchesConfiguredFilters`, not `matches`).
3. **No magic strings across file boundaries.** Anything compared in more than
   one file is an enum.
4. **A catalog entry is a preset, not a type.** "GitHub: On Push" and "Daily at
   9am" are presets. The word `type` belongs to `webhook|schedule|polling|manual`.
   Getting this backwards is what forces the word "mechanism" into a codebase,
   and no human says "mechanism."

Read the model out loud and it should be plain English: *a trigger has a type,
optionally comes from a preset, and points at a target.*

---

## 4. Database schema

Three tables. Create them in this order — `triggers` has an FK to
`trigger_presets`, and `trigger_events` has an FK to `triggers`.

### 4.1 `trigger_presets`

The catalog of ready-made trigger configurations shown in the UI picker.

```php
Schema::create('trigger_presets', function (Blueprint $table): void {
    $table->id();
    $table->string('category');                          // 'github', 'schedule', 'slack'
    $table->string('key')->unique();                     // 'github.push'
    $table->string('name');                              // 'GitHub: On Push'
    $table->text('description')->nullable();
    $table->string('type');                              // webhook|schedule|polling|manual
    $table->string('signature_scheme')->nullable();      // github|stripe|slack
    $table->string('dedupe_header')->nullable();         // 'X-GitHub-Delivery'
    $table->string('dedupe_payload_path')->nullable();   // 'event_id'
    $table->json('config')->nullable();                  // preset config merged under user config
    $table->json('fields')->nullable();                  // UI field definitions, also validated
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index(['category', 'sort_order']);
});
```

`dedupe_header` and `dedupe_payload_path` are how a provider's own delivery
identifier is found. At least one should be set for every webhook preset — a
preset with neither cannot dedupe retries.

### 4.2 `triggers`

```php
Schema::create('triggers', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
    $table->morphs('target');                            // target_type, target_id
    $table->string('type');                              // webhook|schedule|polling|manual
    $table->foreignId('preset_id')->nullable()
          ->constrained('trigger_presets')->nullOnDelete();
    $table->json('config')->nullable();                  // cron, filters, message template
    $table->string('token', 64)->nullable()->unique();   // webhook URL secret
    $table->text('signing_secret')->nullable();          // encrypted cast
    $table->boolean('is_active')->default(true);
    $table->foreignId('credential_id')->nullable()->constrained()->nullOnDelete();
    $table->json('poll_cursor')->nullable();             // ['value' => …]
    $table->unsignedInteger('consecutive_failure_count')->default(0);
    $table->timestamp('last_run_at')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['type', 'is_active']);                // the due-trigger scans
});
```

`token` is unique and indexed — it is the sole lookup key on the public webhook
endpoint, which must stay fast under provider load.

### 4.3 `trigger_events`

The durable inbox. Every inbound event gets a row, including the ones that are
ignored or rejected.

```php
Schema::create('trigger_events', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('trigger_id')->constrained()->cascadeOnDelete();
    $table->string('source');                            // webhook|schedule|polling|manual
    $table->string('status')->default('queued');
    $table->foreignId('run_id')->nullable()->constrained('runs')->nullOnDelete();
    $table->json('payload')->nullable();                 // decoded body the job fires with
    $table->text('payload_snippet')->nullable();         // raw body, capped, for forensics
    $table->json('headers')->nullable();                 // allow-listed only
    $table->text('error')->nullable();
    $table->string('delivery_id')->nullable();
    $table->unsignedInteger('attempts')->default(0);
    $table->unsignedInteger('duplicate_count')->default(0);
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();

    $table->unique(['trigger_id', 'delivery_id']);       // THE dedupe guarantee
    $table->index(['trigger_id', 'created_at']);         // event log listing
    $table->index(['status', 'created_at']);             // the stuck-event scan
});
```

**The unique index is load-bearing.** A read-then-write check cannot dedupe two
concurrent deliveries — both can pass the check before either writes. NULL
`delivery_id` values stay distinct on every supported driver, so events from
providers that send no identifier are never deduped against each other.

`payload` and `payload_snippet` are both kept on purpose: the snippet is the raw
body needed to re-verify a signature after the fact; `payload` is the decoded
copy the job actually fires with.

---

## 5. Enums

### `app/Enums/Triggers/TriggerType.php`

```php
<?php

namespace App\Enums\Triggers;

/**
 * How a trigger fires. Also used for trigger_events.source — an event's source
 * is always the type of the trigger that produced it.
 */
enum TriggerType: string
{
    case Webhook  = 'webhook';
    case Schedule = 'schedule';
    case Polling  = 'polling';
    case Manual   = 'manual';

    /** Only webhooks get a public URL token. */
    public function usesToken(): bool
    {
        return $this === self::Webhook;
    }

    /** Only polling triggers authenticate an outbound request. */
    public function usesCredential(): bool
    {
        return $this === self::Polling;
    }
}
```

### `app/Enums/Triggers/TriggerEventStatus.php`

```php
<?php

namespace App\Enums\Triggers;

/**
 * The life of one inbound event. Because events are stored before any work
 * happens, this status — not the presence of a run — is the record of what
 * became of an event.
 */
enum TriggerEventStatus: string
{
    /** Stored and waiting for a worker. */
    case Queued = 'queued';

    /** A worker has it right now. */
    case Running = 'running';

    /** It started a run; run_id points at it. */
    case Fired = 'fired';

    /** The trigger's config filters rejected it. Not a failure. */
    case Ignored = 'ignored';

    /** The target was a draft or deleted — nothing to run. */
    case Skipped = 'skipped';

    /** Signature verification failed. */
    case Rejected = 'rejected';

    /** Every retry was spent without starting a run. */
    case Failed = 'failed';

    /**
     * A redelivery of something already accepted.
     *
     * Returned as an intake outcome only — never written to the status column,
     * because the row it refers to keeps whatever status it already had.
     */
    case Duplicate = 'duplicate';

    /** Whether this event has reached a state it will never leave on its own. */
    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Queued, self::Running], true);
    }

    public function isQueueable(): bool
    {
        return $this === self::Queued;
    }

    /**
     * Statuses the system has accepted responsibility for but not resolved —
     * the retry-stuck command's search space.
     *
     * @return array<int, string>
     */
    public static function unresolved(): array
    {
        return [self::Queued->value, self::Running->value];
    }
}
```

---

## 6. Models

### `app/Models/Triggers/TriggerPreset.php`

```php
class TriggerPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'category', 'key', 'name', 'description', 'type', 'signature_scheme',
        'dedupe_header', 'dedupe_payload_path', 'config', 'fields',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => TriggerType::class,
            'config' => 'array',
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Merge the preset's config *under* the user's own values, so anything the
     * user set explicitly wins.
     *
     * @param  array<string, mixed>  $userConfig
     * @return array<string, mixed>
     */
    public function mergeConfig(array $userConfig): array
    {
        return array_replace_recursive($this->config ?? [], $userConfig);
    }
}
```

### `app/Models/Triggers/Trigger.php`

```php
class Trigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'target_type', 'target_id', 'type', 'preset_id',
        'config', 'token', 'signing_secret', 'consecutive_failure_count',
        'credential_id', 'poll_cursor', 'is_active', 'last_run_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TriggerType::class,
            'config' => 'array',
            'signing_secret' => 'encrypted',
            'poll_cursor' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function target(): MorphTo      { return $this->morphTo(); }
    public function preset(): BelongsTo    { return $this->belongsTo(TriggerPreset::class); }
    public function credential(): BelongsTo { return $this->belongsTo(Credential::class); }
    public function events(): HasMany      { return $this->hasMany(TriggerEvent::class); }

    public function hasSigningSecret(): bool
    {
        return $this->signing_secret !== null;
    }

    /** A run started. Stamp the time and clear the failure streak. */
    public function markFired(): void
    {
        $this->update(['last_run_at' => now(), 'consecutive_failure_count' => 0]);
    }

    /**
     * Count one failure against the circuit breaker, switching the trigger off
     * once it hits the limit so a broken target can't be hammered forever.
     */
    public function countFailure(): void
    {
        $count = $this->consecutive_failure_count + 1;

        $this->update([
            'consecutive_failure_count' => $count,
            'is_active' => $count >= (int) config('triggers.failures_before_disable')
                ? false
                : $this->is_active,
        ]);
    }
}
```

### `app/Models/Triggers/TriggerEvent.php`

```php
class TriggerEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'trigger_id', 'source', 'status', 'run_id', 'payload', 'payload_snippet',
        'headers', 'error', 'delivery_id', 'attempts', 'duplicate_count', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => TriggerType::class,
            'status' => TriggerEventStatus::class,
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function trigger(): BelongsTo { return $this->belongsTo(Trigger::class); }
    public function run(): BelongsTo     { return $this->belongsTo(Run::class); }

    /**
     * Take ownership of this event for processing, recording the attempt.
     *
     * Returns false when another worker already moved it out of a queueable
     * state. This is the guard that makes a re-dispatched or duplicated job a
     * no-op instead of a second run — it is a conditional UPDATE, not a
     * read-then-write, so two workers cannot both win.
     */
    public function claim(): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', TriggerEventStatus::unresolved())
            ->update([
                'status' => TriggerEventStatus::Running,
                'attempts' => $this->attempts + 1,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $this->refresh();

        return true;
    }

    public function markFired(Run $run): void
    {
        $this->update([
            'status' => TriggerEventStatus::Fired,
            'run_id' => $run->id,
            'error' => null,
            'processed_at' => now(),
        ]);
    }

    public function finish(TriggerEventStatus $status, ?string $error = null): void
    {
        $this->update([
            'status' => $status,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }

    /** Put a stranded event back in line for a fresh job. */
    public function requeue(): void
    {
        $this->update(['status' => TriggerEventStatus::Queued, 'error' => null]);
    }

    /**
     * Whether this event has sat in a non-terminal state long enough to assume
     * the worker that owned it is never coming back.
     */
    public function isStranded(Carbon $queuedBefore, Carbon $runningBefore): bool
    {
        return match ($this->status) {
            TriggerEventStatus::Queued => $this->created_at->lt($queuedBefore),
            TriggerEventStatus::Running => $this->updated_at?->lt($runningBefore) ?? true,
            default => false,
        };
    }
}
```

---

## 7. `TriggerService`

`app/Services/Triggers/TriggerService.php` — the core, roughly 300 lines.

One class rather than five, because the alternatives all made it worse: splitting
"receive" from "fire" means the fast path imports the slow path just to reach
filter matching, and a per-target strategy registry is three files to replace a
six-line `match`. The cost is one large file; the benefit is that the entire
trigger lifecycle is readable top to bottom.

### Public surface

```php
class TriggerService
{
    /** Headers worth keeping for debugging. Never Authorization, never cookies. */
    private const SAFE_HEADERS = [
        'X-GitHub-Event', 'X-GitHub-Delivery', 'X-Hub-Signature-256',
        'Stripe-Signature', 'X-Slack-Signature', 'Content-Type',
    ];

    public function __construct(
        private readonly WorkflowRunner $workflows,
        private readonly AgentChatService $agents,
    ) {}

    public function create(Workspace $workspace, Model $target, User $creator, array $attributes): Trigger;

    /** @return array{outcome: TriggerEventStatus, event: TriggerEvent} */
    public function receive(Trigger $trigger, TriggerType $source, array $payload,
                            ?Request $request = null, ?string $deliveryId = null): array;

    public function reject(Trigger $trigger, Request $request, string $reason): TriggerEvent;
    public function recordFailure(Trigger $trigger, TriggerType $source, string $error): TriggerEvent;

    public function fire(Trigger $trigger, array $payload, TriggerType $source): ?Run;
    public function canRun(Trigger $trigger): bool;
    public function isAlreadyRunning(Trigger $trigger): bool;
}
```

`receive()` returns an array shape rather than a DTO class. The outcome is
deliberately separate from `$event->status`: a redelivery is a `Duplicate`
outcome, but the row it points at is whatever the original became — usually
`Fired`. Collapsing them would make "duplicate of a success" indistinguishable
from a fresh success.

### `create()`

```php
public function create(Workspace $workspace, Model $target, User $creator, array $attributes): Trigger
{
    $preset = isset($attributes['preset_key'])
        ? TriggerPreset::query()
            ->where('key', $attributes['preset_key'])
            ->where('is_active', true)
            ->firstOrFail()
        : null;

    $type = $preset?->type ?? TriggerType::from($attributes['type']);

    return Trigger::create([
        'workspace_id' => $workspace->id,
        'target_type' => $target->getMorphClass(),
        'target_id' => $target->getKey(),
        'type' => $type,
        'preset_id' => $preset?->id,
        'config' => $preset?->mergeConfig($attributes['config'] ?? []) ?? ($attributes['config'] ?? null),
        'token' => $type->usesToken() ? Str::random(40) : null,
        'signing_secret' => $attributes['signing_secret'] ?? null,
        'credential_id' => $type->usesCredential() ? ($attributes['credential_id'] ?? null) : null,
        'poll_cursor' => null,
        'is_active' => $attributes['is_active'] ?? true,
        'created_by' => $creator->id,
    ]);
}
```

### `receive()` — the fast path

```php
public function receive(Trigger $trigger, TriggerType $source, array $payload,
                        ?Request $request = null, ?string $deliveryId = null): array
{
    $deliveryId ??= $request !== null ? $this->deliveryIdFrom($trigger, $request) : null;

    if ($seen = $this->seenBefore($trigger, $deliveryId)) {
        return $seen;
    }

    if ($request !== null && ! $this->passesFilters($trigger, $payload, $request->headers->all())) {
        return $this->recordEvent($trigger, $source, $payload, $request, $deliveryId,
                                  TriggerEventStatus::Ignored);
    }

    // A draft or deleted target cannot become runnable by waiting, so this is
    // settled now rather than by burning queue attempts on it later.
    if (! $this->canRun($trigger)) {
        return $this->recordEvent($trigger, $source, $payload, $request, $deliveryId,
                                  TriggerEventStatus::Skipped, 'Target not runnable');
    }

    $result = $this->recordEvent($trigger, $source, $payload, $request, $deliveryId,
                                 TriggerEventStatus::Queued);

    if ($result['outcome'] === TriggerEventStatus::Queued) {
        FireTriggerEvent::dispatchFor($trigger, $result['event']);
    }

    return $result;
}
```

**Business outcomes are never exceptions here.** The returned outcome says what
happened and each caller maps it onto its own response.

`$deliveryId` can be supplied by the caller instead of read from headers.
Schedule and polling use this to make "this minute" and "this item" exactly-once
through the same unique index that dedupes webhook retries, rather than each
inventing its own guard.

### `receive()` helpers

```php
/**
 * Has this delivery already been accepted? Returns a result if so, null to
 * carry on with a fresh event.
 *
 * A previously failed event is re-queued rather than ignored — the provider
 * resending it is a second chance at that event, which is exactly what a
 * provider retry is for.
 *
 * @return array{outcome: TriggerEventStatus, event: TriggerEvent}|null
 */
private function seenBefore(Trigger $trigger, ?string $deliveryId): ?array
{
    $existing = $this->previousDelivery($trigger, $deliveryId);

    if ($existing === null) {
        return null;
    }

    if ($existing->status === TriggerEventStatus::Failed) {
        $existing->requeue();
        FireTriggerEvent::dispatchFor($trigger, $existing);

        return ['outcome' => TriggerEventStatus::Queued, 'event' => $existing];
    }

    return $this->countDuplicate($existing);
}

/**
 * A fast path only. The unique index on (trigger_id, delivery_id) is what
 * actually guarantees dedupe — two concurrent deliveries can both pass this
 * check before either has written its row.
 */
private function previousDelivery(Trigger $trigger, ?string $deliveryId): ?TriggerEvent
{
    if ($deliveryId === null) {
        return null;
    }

    return TriggerEvent::query()
        ->where('trigger_id', $trigger->id)
        ->where('delivery_id', $deliveryId)
        ->first();
}

/**
 * Duplicates are counted on the row they duplicate rather than stored as new
 * rows — the unique index makes a second row impossible anyway, and
 * "GitHub retried this four times" is the more useful record.
 *
 * @return array{outcome: TriggerEventStatus, event: TriggerEvent}
 */
private function countDuplicate(TriggerEvent $event): array
{
    $event->increment('duplicate_count');

    return ['outcome' => TriggerEventStatus::Duplicate, 'event' => $event];
}

/** @return array{outcome: TriggerEventStatus, event: TriggerEvent} */
private function recordEvent(Trigger $trigger, TriggerType $source, array $payload,
                             ?Request $request, ?string $deliveryId,
                             TriggerEventStatus $status, ?string $error = null): array
{
    $attributes = [
        'trigger_id' => $trigger->id,
        'source' => $source,
        'status' => $status,
        'payload' => $payload,
        'payload_snippet' => $request !== null ? Str::limit($request->getContent(), 5000) : null,
        'headers' => $request !== null ? $this->safeHeaders($request) : null,
        'error' => $error,
        'delivery_id' => $deliveryId,
        'processed_at' => $status->isTerminal() ? now() : null,
    ];

    try {
        return ['outcome' => $status, 'event' => TriggerEvent::create($attributes)];
    } catch (UniqueConstraintViolationException $e) {
        // Another delivery of the same id committed between the check and this
        // insert. Its row is the record of this event.
        $winner = $this->previousDelivery($trigger, $deliveryId);

        if ($winner === null) {
            throw $e;
        }

        return $this->countDuplicate($winner);
    }
}

/** The provider's own delivery identifier, from a header or a payload path. */
private function deliveryIdFrom(Trigger $trigger, Request $request): ?string
{
    $preset = $trigger->preset;

    if ($preset?->dedupe_header !== null) {
        $value = $request->header($preset->dedupe_header);

        if ($value !== null) {
            return $value;
        }
    }

    if ($preset?->dedupe_payload_path !== null) {
        $value = data_get($request->all(), $preset->dedupe_payload_path);

        if (is_scalar($value)) {
            return (string) $value;
        }
    }

    return null;
}

/**
 * All configured filters must match; a trigger with no filters always matches.
 */
private function passesFilters(Trigger $trigger, array $payload, array $headers): bool
{
    foreach ($trigger->config['filters'] ?? [] as $filter) {
        $actual = ($filter['source'] ?? 'payload') === 'header'
            ? ($headers[strtolower($filter['path'])][0] ?? null)
            : data_get($payload, $filter['path']);

        $actual = is_scalar($actual) ? (string) $actual : null;
        $expected = (string) $filter['value'];

        $matches = match ($filter['operator'] ?? 'equals') {
            'not_equals' => $actual !== $expected,
            'contains' => $actual !== null && str_contains($actual, $expected),
            default => $actual === $expected,
        };

        if (! $matches) {
            return false;
        }
    }

    return true;
}

/** @return array<string, string> */
private function safeHeaders(Request $request): array
{
    $headers = [];

    foreach (self::SAFE_HEADERS as $name) {
        $value = $request->header($name);

        if ($value !== null) {
            $headers[$name] = $value;
        }
    }

    return $headers;
}
```

### Logging paths

```php
/**
 * Record a delivery that failed signature verification.
 *
 * Stored WITHOUT its delivery id on purpose: a rejected delivery must not
 * occupy the dedupe slot, or an attacker could block a legitimate delivery
 * simply by guessing its id and sending a badly signed request first.
 */
public function reject(Trigger $trigger, Request $request, string $reason): TriggerEvent
{
    return TriggerEvent::create([
        'trigger_id' => $trigger->id,
        'source' => TriggerType::Webhook,
        'status' => TriggerEventStatus::Rejected,
        'payload_snippet' => Str::limit($request->getContent(), 5000),
        'headers' => $this->safeHeaders($request),
        'error' => $reason,
        'processed_at' => now(),
    ]);
}

/**
 * Record a fault that stopped events being collected at all — an unreachable
 * poll URL, an expired credential — as opposed to one event failing.
 *
 * No delivery id, so it never occupies a dedupe slot: this is a log entry about
 * the attempt, not an event anyone can redeliver.
 */
public function recordFailure(Trigger $trigger, TriggerType $source, string $error): TriggerEvent
{
    return TriggerEvent::create([
        'trigger_id' => $trigger->id,
        'source' => $source,
        'status' => TriggerEventStatus::Failed,
        'error' => $error,
        'processed_at' => now(),
    ]);
}
```

### The firing path

```php
/**
 * Fire a trigger against its workflow or agent. Returns null when the target
 * is not currently runnable.
 *
 * Exceptions propagate untouched. Counting them against the failure streak here
 * would let a single flaky event trip the circuit breaker across its own
 * retries — the streak is advanced by whoever owns the decision that an event
 * is finally, permanently failed: FireTriggerEvent::failed().
 */
public function fire(Trigger $trigger, array $payload, TriggerType $source): ?Run
{
    $target = $trigger->target;

    $run = match (true) {
        $this->isRunnableWorkflow($target) => $this->workflows->start($target, null, $payload, $source->value),
        $this->isRunnableAgent($target) => $this->agents->sendFromTrigger(
            $target, $payload, $source->value, $trigger->config['message'] ?? null,
        ),
        default => null,
    };

    $run !== null && $trigger->markFired();

    return $run;
}

/** Whether firing right now would produce a run. */
public function canRun(Trigger $trigger): bool
{
    $target = $trigger->target;

    return $this->isRunnableWorkflow($target) || $this->isRunnableAgent($target);
}

private function isRunnableWorkflow(mixed $target): bool
{
    return $target instanceof Workflow && $target->status === 'published' && ! $target->trashed();
}

private function isRunnableAgent(mixed $target): bool
{
    return $target instanceof Agent && ! $target->trashed();
}

/**
 * Whether the target already has a non-terminal run in flight. Used only by the
 * manual-run endpoint — see the note in TriggerController::run().
 */
public function isAlreadyRunning(Trigger $trigger): bool
{
    return Run::query()
        ->where('runnable_type', $trigger->target_type)
        ->where('runnable_id', $trigger->target_id)
        ->whereNotIn('status', [RunStatus::Completed, RunStatus::Failed, RunStatus::Cancelled])
        ->exists();
}
```

**Adding a third target type** means one more arm in the `match(true)` and one
more `isRunnableX()` — deliberately not abstracted behind an interface until
there is a third one.

---

## 8. `WebhookSignatureVerifier`

`app/Services/Triggers/WebhookSignatureVerifier.php` — kept separate from
`TriggerService` because it is provider HMAC code with no relationship to the
event lifecycle.

```php
class WebhookSignatureVerifier
{
    /**
     * Verify an inbound request against the preset's signature scheme. Passes
     * when no scheme or no secret is configured — signing is opt-in per preset.
     */
    public function verify(Trigger $trigger, Request $request): bool
    {
        $scheme = $trigger->preset?->signature_scheme;
        $secret = $trigger->signing_secret;

        if ($scheme === null || $secret === null) {
            return true;
        }

        return match ($scheme) {
            'github' => $this->verifyGithub($request, $secret),
            'stripe' => $this->verifyStripe($request, $secret),
            'slack' => $this->verifySlack($request, $secret),
            default => false,
        };
    }
}
```

| Scheme | Header | Computation |
|---|---|---|
| `github` | `X-Hub-Signature-256` | `sha256=` + `hash_hmac('sha256', $rawBody, $secret)` |
| `stripe` | `Stripe-Signature` | Parse `t=`/`v1=`; reject if `abs(time() - t) > 300`; compare against `hash_hmac('sha256', "{$t}.{$rawBody}", $secret)` |
| `slack` | `X-Slack-Signature` + `X-Slack-Request-Timestamp` | Reject if timestamp older than 300s; `v0=` + `hash_hmac('sha256', "v0:{$ts}:{$rawBody}", $secret)` |

Three rules, all mandatory:

1. **Always `hash_equals()`**, never `===`. Timing attacks on HMAC comparison are
   real and cheap.
2. **Always the raw body** (`$request->getContent()`), never the decoded array —
   re-encoding changes bytes and every signature breaks.
3. **Always enforce the timestamp window** where the provider supplies one. A
   valid signature with no freshness check is a replay attack waiting to happen.

---

## 9. Jobs

### `app/Jobs/Triggers/FireTriggerEvent.php`

```php
class FireTriggerEvent implements ShouldQueue
{
    use Queueable;

    /** Real failures tolerated before the event is given up on. */
    public int $maxExceptions = 3;

    /** Generous enough for an agent's model call; the worker timeout matches. */
    public int $timeout = 300;

    public function __construct(public int $eventId, public int $triggerId) {}

    /**
     * Agent runs block on a model call for as long as the model takes, so they
     * get their own queue — a slow agent must not stall workflow events.
     */
    public static function dispatchFor(Trigger $trigger, TriggerEvent $event): void
    {
        $queue = $trigger->target_type === (new Agent)->getMorphClass()
            ? config('triggers.queues.agent')
            : config('triggers.queues.default');

        static::dispatch($event->id, $trigger->id)->onQueue((string) $queue);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('triggers.give_up_after_minutes'));
    }

    /** Quick first, then long enough to ride out a provider outage. */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->triggerId))
                ->releaseAfter((int) config('triggers.retry_when_busy_seconds'))
                ->expireAfter($this->timeout * 2),
        ];
    }

    public function handle(TriggerService $triggers): void
    {
        $event = TriggerEvent::find($this->eventId);
        $trigger = Trigger::with('target')->find($this->triggerId);

        if ($event === null || $trigger === null) {
            return;
        }

        // Already accounted for — a re-dispatch from the retry command or a
        // duplicated job must not start a second run.
        if (! $event->claim()) {
            return;
        }

        if (! $trigger->is_active) {
            $event->finish(TriggerEventStatus::Skipped, 'Trigger is inactive');

            return;
        }

        // Thrown exceptions leave the event `running` on purpose: the retry
        // re-claims it, and failed() settles it once retries are exhausted.
        $run = $triggers->fire($trigger, $event->payload ?? [], $event->source);

        if ($run === null) {
            $event->finish(TriggerEventStatus::Skipped, 'Target not runnable');

            return;
        }

        $event->markFired($run);
    }

    /**
     * Only reached once the retry window or exception budget is spent — which is
     * why the failure streak is counted here rather than per attempt. An event
     * that succeeds on its second try is not a failure.
     */
    public function failed(?Throwable $exception): void
    {
        $event = TriggerEvent::find($this->eventId);

        if ($event === null || $event->status->isTerminal()) {
            return;
        }

        $event->finish(TriggerEventStatus::Failed, $exception?->getMessage() ?? 'Processing failed.');

        // An event that expired without ever being claimed never reached the
        // target, so it says nothing about the target's health — that is queue
        // contention, and counting it would let backlog alone trip the breaker.
        if ($event->attempts > 0) {
            Trigger::find($this->triggerId)?->countFailure();
        }
    }
}
```

**Attempts are bounded by time, not by count.** `WithoutOverlapping` releases the
job back onto the queue while a sibling event for the same trigger is in flight,
and a released job burns an attempt — so a count-based `$tries` limit would
expire a busy trigger's events without ever having tried them. `maxExceptions`
bounds the thing that actually matters: real failures.

### `app/Jobs/Triggers/PollTrigger.php`

```php
class PollTrigger implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    /** Slightly over $timeout, so a slow poll can't race the next tick. */
    public int $uniqueFor = 120;

    public function __construct(public int $triggerId) {}

    public function uniqueId(): string
    {
        return (string) $this->triggerId;
    }

    public function handle(TriggerService $triggers): void
    {
        $trigger = Trigger::with(['preset', 'credential', 'target'])->find($this->triggerId);

        if ($trigger === null || ! $trigger->is_active) {
            return;
        }

        $preset = $trigger->preset?->config ?? [];
        $pollUrl = $preset['poll_url'] ?? null;
        $itemsPath = $preset['items_path'] ?? null;
        $cursorPath = $preset['cursor_path'] ?? null;

        if ($pollUrl === null || $itemsPath === null || $cursorPath === null) {
            return;
        }

        try {
            $response = $this->applyCredential(Http::timeout(15), $trigger)->get($pollUrl);
            $response->throw();
        } catch (Throwable $e) {
            // The poll itself failing is a trigger-level fault (bad URL, dead
            // credential), unlike a single item failing — so it counts here.
            $trigger->countFailure();
            $triggers->recordFailure($trigger, TriggerType::Polling, $e->getMessage());
            report($e);

            return;
        }

        $items = data_get($response->json(), $itemsPath, []);
        $lastCursor = $trigger->poll_cursor['value'] ?? null;
        $maxCursor = $lastCursor;

        foreach ($items as $item) {
            $cursorValue = data_get($item, $cursorPath);

            if (! $this->isNewerThan($cursorValue, $lastCursor)) {
                continue;
            }

            // The item's own cursor is the idempotency key. If this job dies
            // after queuing some items but before the cursor reaches them, the
            // next poll re-offers those items and the unique index rejects
            // them — a crash mid-loop costs nothing and duplicates nothing.
            $triggers->receive(
                $trigger,
                TriggerType::Polling,
                is_array($item) ? $item : ['value' => $item],
                deliveryId: 'poll:'.$cursorValue,
            );

            if ($this->isNewerThan($cursorValue, $maxCursor)) {
                $maxCursor = $cursorValue;
                $trigger->update(['poll_cursor' => ['value' => $maxCursor]]);
            }
        }

        // Stamp on every completed attempt, even with no new items, so the
        // interval check advances. Left untouched on a failed attempt so the
        // next tick retries sooner.
        $trigger->update(['last_run_at' => now()]);
    }

    private function isNewerThan(mixed $value, mixed $baseline): bool;
    private function applyCredential(PendingRequest $request, Trigger $trigger): PendingRequest;
}
```

`applyCredential()` throws on an expired credential, calls
`$credential->touchLastUsed()`, and maps the credential type onto
`withToken()` / `withBasicAuth()` / `withHeaders()`. If the host app has more
than a couple of outbound integrations, promote this to
`Credential::applyTo(PendingRequest): PendingRequest` and use it everywhere.

**Note:** polling only works if presets supply `poll_url`, `items_path`, and
`cursor_path` in their `config`. Seed at least one polling preset or the feature
is unreachable through the catalog.

---

## 10. HTTP layer

### `WebhookController`

`app/Http/Controllers/Api/V1/Triggers/WebhookController.php` — the public
endpoint. It verifies, stores, and acknowledges. **It never starts a run.** That
is what keeps response time flat regardless of how heavy the work behind the
trigger is, and why providers see a prompt 2xx instead of timing out and
disabling the hook.

```php
public function __invoke(Request $request, string $token): JsonResponse
{
    $trigger = Trigger::query()
        ->with(['preset', 'target'])
        ->where('token', $token)
        ->where('type', TriggerType::Webhook)
        ->where('is_active', true)
        ->first();

    if ($trigger === null) {
        return ApiResponse::notFound();
    }

    if (! $this->signatures->verify($trigger, $request)) {
        $this->triggers->reject($trigger, $request, 'Invalid signature');

        return ApiResponse::error('Invalid webhook signature.', Response::HTTP_UNAUTHORIZED);
    }

    ['outcome' => $outcome, 'event' => $event] = $this->triggers->receive(
        $trigger, TriggerType::Webhook, $request->all(), $request,
    );

    return match ($outcome) {
        TriggerEventStatus::Duplicate => ApiResponse::success(['event_id' => $event->id], 'Duplicate delivery ignored.'),
        TriggerEventStatus::Ignored   => ApiResponse::success(['event_id' => $event->id], 'Event ignored by trigger filters.'),
        TriggerEventStatus::Skipped   => ApiResponse::error('This trigger is not currently runnable.', Response::HTTP_CONFLICT),
        default => ApiResponse::success(['event_id' => $event->id], 'Event accepted.', Response::HTTP_ACCEPTED),
    };
}
```

**Ignored and duplicate deliveries answer 200, not 4xx.** Providers fan every
event in a stream at one URL; answering an error to the ones this trigger does
not want makes them retry, and eventually disable the hook.

There is no lock anywhere in this controller. Dedupe is the unique index, which
is both race-free and free of the multi-second block a cache lock imposes on a
legitimate delivery.

### `TriggerController`

One controller for both parent types. `$parent` binds to `{agent}` or
`{workflow}` from the route.

```php
class TriggerController extends Controller
{
    public function index(Request $r, Workspace $w, Model $parent): JsonResponse;
    public function store(TriggerRequest $r, Workspace $w, Model $parent, TriggerService $triggers): JsonResponse;
    public function update(TriggerRequest $r, Workspace $w, Model $parent, Trigger $t): JsonResponse;
    public function destroy(Request $r, Workspace $w, Model $parent, Trigger $t): JsonResponse;
    public function run(Request $r, Workspace $w, Model $parent, Trigger $t, TriggerService $triggers): JsonResponse;
    public function rotateToken(Request $r, Workspace $w, Model $parent, Trigger $t): JsonResponse;
    public function events(Request $r, Workspace $w, Model $parent, Trigger $t): JsonResponse;

    /** Workspace ownership + parent ownership, once, instead of in every method. */
    private function authorizeTrigger(Workspace $w, Model $parent, ?Trigger $t = null): void
    {
        abort_if($parent->workspace_id !== $w->id, 404);

        if ($t !== null) {
            abort_if($t->target_id !== $parent->getKey()
                  || $t->target_type !== $parent->getMorphClass(), 404);
        }
    }
}
```

Permissions per method: `index`/`events` → `TriggerEventView`/`TriggerView`;
`store`/`update`/`destroy` → `TriggerManage`; `run` → `TriggerRun`;
`rotateToken` → `TriggerTokenRotate`.

`run()` is the **only** place that calls `isAlreadyRunning()`:

```php
if ($triggers->isAlreadyRunning($trigger)) {
    return ApiResponse::error('A run is already in progress for this trigger target.',
                              Response::HTTP_CONFLICT);
}
```

A person clicking Run twice should be told the target is busy. A provider
redelivering a webhook should have its event queued, not discarded. This is a UI
affordance, not a durability mechanism — do not add it to the automated paths.

`rotateToken()` rejects non-webhook triggers with 422, then writes a fresh
`Str::random(40)`.

### `TriggerPresetController`

Single-action, returns the catalog grouped by category, ordered by `sort_order`,
exposing `key`, `name`, `description`, `type`, `config`, `fields`.

### `TriggerRequest`

One form request for create and update. The rule sets are ~90% shared; the
create-only additions are guarded by `isCreating()`.

```php
public function rules(): array
{
    $rules = [
        'config' => ['sometimes', 'array'],
        'config.cron' => ['sometimes', 'string', $this->validCron()],
        'config.message' => ['sometimes', 'string', 'max:2000'],
        'config.filters' => ['sometimes', 'array'],
        'config.filters.*.source' => ['required', Rule::in(['payload', 'header'])],
        'config.filters.*.path' => ['required', 'string', 'max:255'],
        'config.filters.*.operator' => ['sometimes', Rule::in(['equals', 'not_equals', 'contains'])],
        'config.filters.*.value' => ['required', 'string', 'max:500'],
        'signing_secret' => ['sometimes', 'nullable', 'string', 'max:500'],
        'credential_id' => ['sometimes', 'nullable',
            Rule::exists('credentials', 'id')->where('workspace_id', $this->route('workspace')?->id)],
        'is_active' => ['sometimes', 'boolean'],
    ];

    if ($this->isCreating()) {
        $rules['type'] = ['required_without:preset_key', Rule::enum(TriggerType::class)];
        $rules['preset_key'] = ['required_without:type', 'string',
            Rule::exists('trigger_presets', 'key')->where('is_active', true)];
        $rules['config.cron'][0] = 'required_if:type,schedule';
    }

    return $rules;
}
```

`after()` handles three cross-field checks:

1. A `schedule` preset with no cron in its own config requires one from the user.
2. A preset with a `signature_scheme` requires a `signing_secret`.
3. `validatePresetFields()` enforces the preset's `fields` array
   (`name`/`label`/`type`/`required`) against the submitted config — otherwise
   that array is decorative UI metadata and nothing actually requires the fields.

### Resources

```php
// TriggerResource
'id', 'workspace_id', 'type',
'preset' => whenLoaded('preset', fn () => ['key' =>, 'name' =>, 'category' =>]),
'config', 'token',
'webhook_url' => when($this->type === TriggerType::Webhook && $this->token !== null,
                      fn () => url("/api/v1/hooks/{$this->token}")),
'has_signing_secret', 'credential_id', 'consecutive_failure_count',
'is_active', 'last_run_at', 'created_at',

// TriggerEventResource
'id', 'source', 'status', 'run_id', 'error', 'delivery_id',
'attempts', 'duplicate_count', 'processed_at', 'created_at',
```

Never expose `signing_secret` — only the `has_signing_secret` boolean.

---

## 11. Routes

```php
// routes/api.php — public, authenticated by the trigger's own token
Route::post('hooks/{token}', WebhookController::class)
    ->middleware('throttle:trigger-hooks')
    ->name('hooks.trigger');

// routes/api/catalog.php
Route::get('catalog/trigger-presets', TriggerPresetController::class)->name('catalog.trigger-presets');

// routes/api/agents.php and routes/api/workflows.php — identical bodies
Route::prefix('{agent}/triggers')->as('triggers.')->group(function (): void {
    Route::get('/', [TriggerController::class, 'index'])->name('index');
    Route::post('/', [TriggerController::class, 'store'])->name('store');
    Route::put('{trigger}', [TriggerController::class, 'update'])->name('update');
    Route::delete('{trigger}', [TriggerController::class, 'destroy'])->name('destroy');
    Route::post('{trigger}/run', [TriggerController::class, 'run'])->name('run');
    Route::post('{trigger}/rotate-token', [TriggerController::class, 'rotateToken'])->name('rotate-token');
    Route::get('{trigger}/events', [TriggerController::class, 'events'])->name('events.index');
});
```

Rate limiter in `AppServiceProvider::boot()`:

```php
RateLimiter::for('trigger-hooks', fn (Request $request): Limit => Limit::perMinute(
    (int) config('triggers.hook_rate_limit_per_minute')
)->by($request->route('token') ?? $request->ip()));
```

Keying by token rather than IP is deliberate: one noisy provider must not
rate-limit every other trigger sharing its egress IPs.

---

## 12. Console commands

### `RunDueTriggersCommand` — `triggers:run-due`

```php
public function handle(TriggerService $triggers): int
{
    $queued = $this->queueDueSchedules($triggers) + $this->queueDuePolls();

    $this->info("Queued {$queued} trigger event(s).");

    return self::SUCCESS;
}
```

**Schedules.** For each active `schedule` trigger whose cron matches this minute:

```php
$triggers->receive(
    $trigger,
    TriggerType::Schedule,
    ['scheduled_at' => now()->toIso8601String()],
    deliveryId: 'schedule:'.now()->format('Y-m-d H:i'),
);
```

Double-firing is prevented by the delivery id, not by locking — the id is derived
from the minute the trigger is due for, so a second invocation in the same minute
collides on the unique index and is recorded as a duplicate. That holds across
concurrent servers, which a per-process check could not.

`CronExpression::isDue()` only checks the exact current minute. Windows missed
during downtime are **not** backfilled. That is a deliberate choice — decide
consciously if the new project needs different behaviour.

**Polls.** For each active `polling` trigger whose interval has elapsed
(`last_run_at + interval` is in the past), dispatch `PollTrigger`. The interval
comes from the preset's `config['poll_interval_minutes']`, falling back to
`config('triggers.poll_every_minutes')`.

This command **starts no runs**. It writes a row per due trigger and returns, so
the every-minute tick costs the same whether one trigger is due or a thousand.

### `RetryStuckEventsCommand` — `triggers:retry-stuck`

```php
public function handle(): int
{
    $queuedBefore = now()->subMinutes((int) config('triggers.stuck.queued_after_minutes'));
    $runningBefore = now()->subMinutes((int) config('triggers.stuck.running_after_minutes'));

    TriggerEvent::query()
        ->with('trigger')
        ->whereIn('status', TriggerEventStatus::unresolved())
        ->where(fn ($q) => $q
            ->where(fn ($i) => $i->where('status', TriggerEventStatus::Queued)
                                 ->where('created_at', '<', $queuedBefore))
            ->orWhere(fn ($i) => $i->where('status', TriggerEventStatus::Running)
                                   ->where('updated_at', '<', $runningBefore)))
        ->oldest()
        ->limit((int) config('triggers.stuck.batch_size'))
        ->get()
        ->each(function (TriggerEvent $event): void {
            $trigger = $event->trigger;

            if ($trigger === null || ! $trigger->is_active) {
                $event->finish(TriggerEventStatus::Skipped, 'Trigger unavailable at retry time');

                return;
            }

            // Back to queued first — claim() only accepts non-terminal events,
            // and a stale `running` row looks identical to a live one.
            $event->requeue();

            FireTriggerEvent::dispatchFor($trigger, $event);
        });

    return self::SUCCESS;
}
```

The grace periods must comfortably exceed normal processing time or this command
will race live work.

### Scheduler — `routes/console.php`

```php
Schedule::command('triggers:run-due')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('triggers:retry-stuck')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
```

`onOneServer()` requires a shared cache store (Redis or database). Without it,
every app server runs both commands.

---

## 13. Configuration

`config/triggers.php`:

```php
return [
    'failures_before_disable' => env('TRIGGERS_FAILURES_BEFORE_DISABLE', 5),
    'poll_every_minutes' => env('TRIGGERS_POLL_EVERY_MINUTES', 5),
    'hook_rate_limit_per_minute' => env('TRIGGERS_HOOK_RATE_LIMIT_PER_MINUTE', 60),

    /*
    | Trigger events are worked off dedicated queues so a slow agent run — which
    | blocks for as long as the model takes — cannot stall workflow events or
    | the rest of the application's jobs behind it.
    */
    'queues' => [
        'default' => env('TRIGGERS_QUEUE', 'triggers'),
        'agent' => env('TRIGGERS_AGENT_QUEUE', 'triggers-agent'),
    ],

    /*
    | give_up_after_minutes bounds how long one event may keep retrying.
    | retry_when_busy_seconds is how long it waits when another event for the
    | same trigger is already in flight.
    */
    'give_up_after_minutes' => env('TRIGGERS_GIVE_UP_AFTER_MINUTES', 30),
    'retry_when_busy_seconds' => env('TRIGGERS_RETRY_WHEN_BUSY_SECONDS', 10),

    /*
    | An event is stored before its job is dispatched, so a lost dispatch or a
    | crashed worker leaves a row stranded. These grace periods decide when to
    | assume the job is never coming back. Both must comfortably exceed normal
    | processing time or the retry command will race live work.
    */
    'stuck' => [
        'queued_after_minutes' => env('TRIGGERS_STUCK_QUEUED_MINUTES', 5),
        'running_after_minutes' => env('TRIGGERS_STUCK_RUNNING_MINUTES', 15),
        'batch_size' => env('TRIGGERS_STUCK_BATCH', 500),
    ],
];
```

### Horizon

The agent worker's `timeout` must be at least `FireTriggerEvent::$timeout` (300),
or the worker kills the job mid-model-call:

```php
'trigger-supervisor' => [
    'connection' => 'redis',
    'queue' => ['triggers'],
    'balance' => 'auto',
    'maxProcesses' => 10,
    'timeout' => 90,
],
'trigger-agent-supervisor' => [
    'connection' => 'redis',
    'queue' => ['triggers-agent'],
    'balance' => 'auto',
    'maxProcesses' => 5,
    'timeout' => 320,
],
```

Also set `retry_after` in `config/queue.php` **higher** than the longest job
timeout, or the queue will hand the same job to a second worker while the first
is still working it.

---

## 14. Preset catalog seeder

`database/seeders/TriggerPresetSeeder.php`:

```php
public function run(): void
{
    $sort = 0;

    foreach ($this->catalog() as $preset) {
        TriggerPreset::updateOrCreate(
            ['key' => $preset['key']],
            [...$preset, 'is_active' => true, 'sort_order' => $sort++],
        );
    }
}
```

`updateOrCreate` keyed on `key` makes the seeder re-runnable on every deploy —
new presets appear, existing ones update, user triggers keep their `preset_id`.

Starting catalog:

| Category | Key | Type | Notes |
|---|---|---|---|
| schedule | `schedule.hourly` | schedule | `config.cron = '0 * * * *'` |
| schedule | `schedule.daily` | schedule | `'0 9 * * *'`, cron field overridable |
| schedule | `schedule.weekly` | schedule | `'0 9 * * 1'` |
| schedule | `schedule.monthly` | schedule | `'0 9 1 * *'` |
| schedule | `schedule.cron` | schedule | cron field required |
| github | `github.push` | webhook | `signature_scheme: github`, `dedupe_header: X-GitHub-Delivery`, filter on `X-GitHub-Event` |
| github | `github.pull_request` | webhook | same, event filter `pull_request` |
| github | `github.issues` | webhook | same, event filter `issues` |
| stripe | `stripe.payment_succeeded` | webhook | `signature_scheme: stripe`, `dedupe_payload_path: id`, filter `type = payment_intent.succeeded` |
| stripe | `stripe.subscription_created` | webhook | same, filter `type = customer.subscription.created` |
| slack | `slack.message` | webhook | `signature_scheme: slack`, **`dedupe_payload_path: event_id`** |
| slack | `slack.app_mention` | webhook | same, filter `event.type = app_mention` |
| generic | `webhook.generic` | webhook | no signature, no dedupe |

**Set `dedupe_payload_path: 'event_id'` on every Slack preset.** Slack sends no
delivery header, but its Events API includes `event_id` on every delivery. Do not
be tempted to dedupe on `X-Slack-Retry-Num` with a time-window heuristic — it is
less accurate and costs an extra query on every single delivery.

Add at least one polling preset with `poll_url`, `items_path`, `cursor_path`, and
`poll_interval_minutes` in its config, or the polling type is unreachable.

---

## 15. Build order

Each step ends green. Do not proceed on red.

| # | Step | Deliverable |
|---|---|---|
| 1 | Migrations + enums | 3 tables, `TriggerType`, `TriggerEventStatus`. `migrate:fresh` passes |
| 2 | Models + factories | `Trigger`, `TriggerEvent`, `TriggerPreset` + factories with provider states |
| 3 | Preset seeder | `migrate:fresh --seed` populates the catalog |
| 4 | `TriggerService` | `create`, `receive`, `reject`, `recordFailure`, `fire`, `canRun`, `isAlreadyRunning` |
| 5 | `FireTriggerEvent` | Queued firing, `claim()` guard, `failed()` handler |
| 6 | `WebhookSignatureVerifier` + `WebhookController` | Public endpoint end-to-end |
| 7 | `TriggerRequest` + `TriggerController` + resources + routes | Full CRUD API |
| 8 | `RunDueTriggersCommand` + scheduler | Schedule triggers fire |
| 9 | `PollTrigger` + a polling preset | Polling works |
| 10 | `RetryStuckEventsCommand` | Recovery path |
| 11 | Horizon config + queue tuning | Production-ready |

Steps 1–7 are the minimum viable system (webhook + manual). 8–11 add the
remaining types and durability.

---

## 16. Test plan

Feature tests, one file per behaviour. `php artisan make:test --pest {name}`.

| File | Asserts |
|---|---|
| `TriggerTest` | Create webhook/schedule/manual triggers; token generated for webhooks only; cron validated; permissions enforced |
| `UpdateTriggerTest` | Config merges; `is_active` toggles; cross-workspace update 404s |
| `TriggerCatalogTest` | Presets grouped by category; preset config merges under user config; preset `fields` validation rejects a missing required field |
| `RotateTriggerTokenTest` | New token issued; old token 404s; non-webhook 422s |
| `ManualTriggerRunTest` | `run` queues an event and returns 202; second call while a run is in flight returns 409; draft target returns 409 |
| `WebhookIntakeTest` | Valid POST → 202 + `queued` event + `FireTriggerEvent` dispatched; unknown token → 404; inactive trigger → 404 |
| `WebhookSignatureTest` | Valid GitHub/Stripe/Slack signatures pass; tampered body fails and writes a `rejected` event; stale Stripe/Slack timestamps fail |
| `WebhookDedupeTest` | Same delivery id twice → one row, `duplicate_count = 1`, 200; Slack dedupes on `event_id`; a `failed` event redelivered is re-queued |
| `TriggerFilterTest` | `equals`/`not_equals`/`contains` on payload and header; non-matching → `ignored`, no job dispatched |
| `ScheduleTriggerTest` | Due cron queues an event; running the command twice in one minute produces one row; non-due cron produces none |
| `PollingTriggerTest` | New items queue one event each; cursor advances; re-poll of the same items queues nothing; failed poll counts a failure and records an event |
| `FireTriggerEventTest` | Fires a workflow run and marks `fired`; inactive trigger → `skipped`; a terminal event is not re-fired; exhausted retries → `failed` |
| `RetryStuckEventsTest` | Stranded `queued` past grace is re-dispatched; stranded `running` is re-dispatched; a fresh event is left alone; deleted trigger → `skipped` |
| `TriggerCircuitBreakerTest` | Failures increment the count; hitting the limit sets `is_active = false`; a success clears the streak |
| `TriggerEventLogTest` | Events listed newest first, paginated, scoped to the trigger; permission enforced |
| `TriggerRateLimitTest` | Hook endpoint 429s past the configured limit |

Use `Queue::fake()` for intake tests and `Bus::fake()` where you need to assert
dispatch without running. Test `FireTriggerEvent::handle()` directly rather than
through the queue.

**Three tests worth writing carefully**, because they encode the invariants:

1. **Dedupe under concurrency** — insert the same `delivery_id` twice and assert
   one row plus an incremented `duplicate_count`, exercising the
   `UniqueConstraintViolationException` catch, not just the read-check.
2. **Claim is exclusive** — call `claim()` twice on the same event and assert the
   second returns `false`.
3. **Event survives a lost dispatch** — create a `queued` event with no job, run
   `triggers:retry-stuck` past the grace period, assert the job is dispatched.

---

## 17. Design decisions worth keeping

These are the parts that took real debugging to arrive at. Carry the reasoning
over, not just the code.

| Decision | Why |
|---|---|
| Store the event before dispatching the job | The row outlives the job. Covers lost dispatches, flushed queues, killed workers — cases retries cannot help with because no job exists |
| Unique index on `(trigger_id, delivery_id)` | Dedupe as a write-time guarantee. A read-then-write check loses to concurrent deliveries |
| Rejected deliveries stored **without** a delivery id | Otherwise an attacker blocks a legitimate delivery by guessing its id and sending a badly signed request first |
| `maxExceptions` instead of `$tries` | `WithoutOverlapping` releases burn attempts, so a count limit expires a busy trigger's events without ever trying them |
| Circuit breaker counted in `failed()`, not per attempt | An event that succeeds on retry two is not a failure. Counting per attempt lets one flaky event trip the breaker alone |
| `$event->attempts > 0` guard in `failed()` | An event that expired unclaimed never reached the target — that is queue contention, and counting it lets backlog alone disable a healthy trigger |
| Ignored/duplicate answer 200, not 4xx | Providers fan all events at one URL. Errors on unwanted events cause retries, then hook disabling |
| Agent runs on their own queue | An agent blocks for as long as the model takes; workflow events must not queue behind it |
| `isAlreadyRunning()` only on the manual path | A person double-clicking wants to be told "busy". A provider redelivering wants its event queued |
| Terminal check inside `claim()` | Makes a re-dispatch idempotent. Without it, the retry command can start a second run for an event that already fired |
| Schedule dedupe by minute-derived id | Works across concurrent servers, which a per-process lock does not |

---

## 18. Deployment checklist

- [ ] Queue driver is not `sync`
- [ ] Workers running for both `triggers` and `triggers-agent`
- [ ] Agent worker timeout ≥ 320s; `queue.retry_after` > longest job timeout
- [ ] Scheduler running (`schedule:run` every minute, or `schedule:work`)
- [ ] Shared cache store configured for `onOneServer()`
- [ ] `APP_KEY` set and stable — `signing_secret` uses the `encrypted` cast, and
      rotating the key without re-encrypting makes every stored secret unreadable
- [ ] Preset seeder wired into the deploy pipeline
- [ ] Webhook URL reachable from the public internet and **not** behind auth
      middleware
- [ ] Retention policy for `trigger_events` — this table grows without bound;
      add a prune command before it becomes a problem
