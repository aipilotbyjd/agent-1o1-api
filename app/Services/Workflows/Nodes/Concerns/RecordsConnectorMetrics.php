<?php

namespace App\Services\Workflows\Nodes\Concerns;

use App\Models\Runs\Run;
use App\Services\Runs\ConnectorMetricRecorder;

/**
 * Success/latency reporting for nodes that call out to an external system.
 */
trait RecordsConnectorMetrics
{
    /**
     * @param  float  $startedAt  A `microtime(true)` reading from before the call.
     */
    protected function recordMetric(Run $run, bool $success, float $startedAt): void
    {
        app(ConnectorMetricRecorder::class)->record(
            $run->workspace_id,
            $this->type(),
            $success,
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
