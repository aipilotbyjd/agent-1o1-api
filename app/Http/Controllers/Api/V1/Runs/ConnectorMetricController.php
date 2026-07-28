<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Runs\ConnectorMetricResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\ConnectorMetric;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectorMetricController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::RunView);

        $metrics = ConnectorMetric::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->query('connector'), fn ($query, $connector) => $query->where('connector', $connector))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->get();

        return ApiResponse::success(ConnectorMetricResource::collection($metrics));
    }

    public function summary(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::RunView);

        $summary = ConnectorMetric::query()
            ->where('workspace_id', $workspace->id)
            ->select('connector')
            ->selectRaw('SUM(total_calls) as total_calls')
            ->selectRaw('SUM(success_calls) as success_calls')
            ->selectRaw('SUM(failed_calls) as failed_calls')
            ->selectRaw('SUM(total_duration_ms) as total_duration_ms')
            ->groupBy('connector')
            ->orderByDesc(DB::raw('SUM(total_calls)'))
            ->get()
            ->map(fn (ConnectorMetric $row): array => [
                'connector' => $row->connector,
                'total_calls' => (int) $row->total_calls,
                'success_calls' => (int) $row->success_calls,
                'failed_calls' => (int) $row->failed_calls,
                'failure_rate' => (int) $row->total_calls > 0
                    ? round((int) $row->failed_calls / (int) $row->total_calls, 4)
                    : 0.0,
                'avg_duration_ms' => (int) $row->total_calls > 0
                    ? (int) round((int) $row->total_duration_ms / (int) $row->total_calls)
                    : 0,
            ]);

        return ApiResponse::success($summary);
    }
}
