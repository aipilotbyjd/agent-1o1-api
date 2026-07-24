<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notification\StoreNotificationChannelRequest;
use App\Http\Requests\Api\V1\Notification\UpdateNotificationChannelRequest;
use App\Http\Resources\V1\NotificationChannelResource;
use App\Http\Responses\ApiResponse;
use App\Models\NotificationChannel;
use App\Models\Workspace;
use App\Notifications\Channels\WorkspaceWebhookChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationChannelController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            NotificationChannelResource::collection($workspace->notificationChannels()->latest()->get()),
        );
    }

    public function store(StoreNotificationChannelRequest $request, Workspace $workspace): JsonResponse
    {
        $channel = $workspace->notificationChannels()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return ApiResponse::created(new NotificationChannelResource($channel), 'Notification channel created.');
    }

    public function update(UpdateNotificationChannelRequest $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureChannelBelongsToWorkspace($workspace, $notificationChannel);

        $notificationChannel->update($request->validated());

        return ApiResponse::success(new NotificationChannelResource($notificationChannel), 'Notification channel updated.');
    }

    public function destroy(Request $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureChannelBelongsToWorkspace($workspace, $notificationChannel);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $notificationChannel->delete();

        return ApiResponse::success(null, 'Notification channel deleted.');
    }

    public function test(Request $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureChannelBelongsToWorkspace($workspace, $notificationChannel);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $result = (new WorkspaceWebhookChannel)->deliverTest($notificationChannel);

        return $result['ok']
            ? ApiResponse::success(null, $result['message'])
            : ApiResponse::error($result['message']);
    }

    private function ensureChannelBelongsToWorkspace(Workspace $workspace, NotificationChannel $notificationChannel): void
    {
        abort_if($notificationChannel->workspace_id !== $workspace->id, 404);
    }
}
