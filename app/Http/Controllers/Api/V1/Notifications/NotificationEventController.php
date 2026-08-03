<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Enums\Notifications\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationEventController extends Controller
{
    /**
     * List every event a user may toggle, so clients can render a settings screen
     * without hard-coding the catalogue.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(NotificationEvent::catalog());
    }
}
