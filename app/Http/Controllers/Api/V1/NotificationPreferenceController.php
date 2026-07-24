<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\NotificationPreference;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $preferences = NotificationPreference::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->get();

        return ApiResponse::success($preferences);
    }

    public function upsert(Request $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validate([
            'event_key' => ['required', 'string', 'max:100'],
            'in_app' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => ['integer', 'exists:notification_channels,id'],
        ]);

        $preference = NotificationPreference::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'event_key' => $data['event_key'],
            ],
            [
                'in_app' => $data['in_app'] ?? true,
                'email' => $data['email'] ?? false,
                'channel_ids' => $data['channel_ids'] ?? null,
            ],
        );

        return ApiResponse::success($preference, 'Notification preference saved.');
    }
}
