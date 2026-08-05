<?php

use App\Enums\Triggers\TriggerEventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn trigger_events from a write-once audit log into the durable inbox that
     * drives processing: events are stored on arrival and worked off a queue, so
     * they need a lifecycle status, the payload to replay, and attempt bookkeeping.
     */
    public function up(): void
    {
        Schema::table('trigger_events', function (Blueprint $table): void {
            $table->string('status')->default(TriggerEventStatus::Pending->value)->after('source');

            // The decoded payload the queued job fires with. payload_snippet stays
            // as the raw body for signature forensics; this is the functional copy.
            $table->json('payload')->nullable()->after('run_id');

            $table->unsignedInteger('attempts')->default(0)->after('payload_snippet');

            // Retries of an accepted delivery are counted here rather than stored as
            // their own rows — the unique index below makes a second row impossible.
            $table->unsignedInteger('duplicate_count')->default(0)->after('attempts');

            $table->timestamp('processed_at')->nullable()->after('duplicate_count');

            // Rows now mutate as they move through the lifecycle.
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // Backfill before the boolean goes away so existing history keeps its meaning.
        DB::table('trigger_events')->where('matched', true)->update([
            'status' => TriggerEventStatus::Matched->value,
        ]);

        DB::table('trigger_events')->where('matched', false)->update([
            'status' => TriggerEventStatus::Filtered->value,
        ]);

        Schema::table('trigger_events', function (Blueprint $table): void {
            $table->dropColumn('matched');

            // Makes dedupe a write-time guarantee instead of a read-then-write race.
            // NULL delivery ids stay distinct on every supported driver, so events
            // from providers that send no delivery id are never deduped against.
            $table->unique(['trigger_id', 'delivery_id']);

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('trigger_events', function (Blueprint $table): void {
            $table->boolean('matched')->default(true)->after('source');
        });

        DB::table('trigger_events')
            ->whereIn('status', [TriggerEventStatus::Matched->value, TriggerEventStatus::Processing->value])
            ->update(['matched' => true]);

        DB::table('trigger_events')
            ->whereNotIn('status', [TriggerEventStatus::Matched->value, TriggerEventStatus::Processing->value])
            ->update(['matched' => false]);

        Schema::table('trigger_events', function (Blueprint $table): void {
            $table->dropUnique(['trigger_id', 'delivery_id']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['status', 'payload', 'attempts', 'duplicate_count', 'processed_at', 'updated_at']);
        });
    }
};
