<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\StoreLogStreamingConfigRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateLogStreamingConfigRequest;
use App\Http\Resources\V1\Workspaces\LogStreamingConfigResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\LogStreamingConfig;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class LogStreamingConfigController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkspaceUpdate);

        return ApiResponse::success(
            LogStreamingConfigResource::collection($workspace->logStreamingConfigs()->latest()->get()),
        );
    }

    public function store(StoreLogStreamingConfigRequest $request, Workspace $workspace): JsonResponse
    {
        $config = $workspace->logStreamingConfigs()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new LogStreamingConfigResource($config), 'Log streaming config created.');
    }

    public function update(UpdateLogStreamingConfigRequest $request, Workspace $workspace, LogStreamingConfig $logStreamingConfig): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $logStreamingConfig);

        $logStreamingConfig->update($request->validated());

        return ApiResponse::success(new LogStreamingConfigResource($logStreamingConfig->fresh()), 'Log streaming config updated.');
    }

    public function destroy(Request $request, Workspace $workspace, LogStreamingConfig $logStreamingConfig): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $logStreamingConfig);

        $this->requirePermission(Permission::WorkspaceUpdate);

        $logStreamingConfig->delete();

        return ApiResponse::success(null, 'Log streaming config deleted.');
    }

    public function test(Request $request, Workspace $workspace, LogStreamingConfig $logStreamingConfig): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $logStreamingConfig);

        $this->requirePermission(Permission::WorkspaceUpdate);

        try {
            $response = Http::timeout(10)
                ->withHeaders($logStreamingConfig->headers ?? [])
                ->post($logStreamingConfig->endpoint, [
                    'type' => 'test',
                    'workspace_id' => $workspace->id,
                    'message' => 'Test delivery from log streaming config.',
                    'sent_at' => now()->toIso8601String(),
                ]);
        } catch (Throwable $exception) {
            return ApiResponse::error('Delivery failed: '.$exception->getMessage(), 502);
        }

        if ($response->failed()) {
            return ApiResponse::error("Destination responded with HTTP {$response->status()}.", 502);
        }

        $logStreamingConfig->update(['last_delivered_at' => now()]);

        return ApiResponse::success(null, 'Test payload delivered.');
    }
}
