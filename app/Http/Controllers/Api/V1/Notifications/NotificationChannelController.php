<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\StoreNotificationChannelRequest;
use App\Http\Requests\Api\V1\Notifications\UpdateNotificationChannelRequest;
use App\Http\Resources\V1\Notifications\NotificationChannelResource;
use App\Http\Responses\ApiResponse;
use App\Models\Notifications\NotificationChannel;
use App\Models\Workspaces\Workspace;
use App\Notifications\Channels\WorkspaceWebhookChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationChannelController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::NotificationChannelView);

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
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);

        $notificationChannel->update($request->validated());

        return ApiResponse::success(new NotificationChannelResource($notificationChannel), 'Notification channel updated.');
    }

    public function destroy(Request $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);

        $this->requirePermission(Permission::NotificationChannelManage);

        $notificationChannel->delete();

        return ApiResponse::success(null, 'Notification channel deleted.');
    }

    public function test(Request $request, Workspace $workspace, NotificationChannel $notificationChannel): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $notificationChannel);

        $this->requirePermission(Permission::NotificationChannelManage);

        $result = (new WorkspaceWebhookChannel)->deliverTest($notificationChannel);

        return $result['ok']
            ? ApiResponse::success(null, $result['message'])
            : ApiResponse::error($result['message']);
    }
}
