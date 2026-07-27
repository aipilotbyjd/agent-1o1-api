<?php

namespace App\Services;

use App\Models\Trigger;
use App\Models\TriggerType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TriggerBuilder
{
    /**
     * Create a trigger for a workflow or agent, optionally from a catalog type
     * whose preset config (cron, filters) is merged under the user's config.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(Workspace $workspace, Model $target, User $creator, array $validated): Trigger
    {
        $triggerType = isset($validated['trigger_type_key'])
            ? TriggerType::query()->where('key', $validated['trigger_type_key'])->where('is_active', true)->firstOrFail()
            : null;

        $mechanism = $triggerType?->mechanism ?? $validated['type'];
        $config = $triggerType?->buildConfig($validated['config'] ?? []) ?? ($validated['config'] ?? null);

        return Trigger::create([
            'workspace_id' => $workspace->id,
            'triggerable_type' => $target->getMorphClass(),
            'triggerable_id' => $target->getKey(),
            'type' => $mechanism,
            'trigger_type_id' => $triggerType?->id,
            'config' => $config,
            'token' => $mechanism === 'webhook' ? Str::random(40) : null,
            'signing_secret' => $validated['signing_secret'] ?? null,
            'credential_id' => $mechanism === 'polling' ? ($validated['credential_id'] ?? null) : null,
            'poll_cursor' => null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $creator->id,
        ]);
    }
}
