<?php

namespace App\Services\Runs;

use App\Models\Runs\ConnectorMetric;
use Illuminate\Support\Facades\DB;

/**
 * Rolls tool/connector call outcomes up into one row per workspace + connector + day,
 * so per-connector reliability and latency can be charted without scanning run logs.
 */
class ConnectorMetricRecorder
{
    public function record(int $workspaceId, string $connector, bool $success, int $durationMs): void
    {
        DB::transaction(function () use ($workspaceId, $connector, $success, $durationMs): void {
            $metric = ConnectorMetric::query()
                ->where('workspace_id', $workspaceId)
                ->where('connector', $connector)
                ->whereDate('date', now()->toDateString())
                ->lockForUpdate()
                ->first();

            if ($metric === null) {
                $metric = ConnectorMetric::create([
                    'workspace_id' => $workspaceId,
                    'connector' => $connector,
                    'date' => now()->toDateString(),
                ]);
            }

            $metric->increment('total_calls');
            $metric->increment($success ? 'success_calls' : 'failed_calls');
            $metric->increment('total_duration_ms', max(0, $durationMs));
        });
    }
}
