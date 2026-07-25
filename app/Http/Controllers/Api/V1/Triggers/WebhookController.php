<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Trigger;
use App\Services\TriggerFiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function __construct(public TriggerFiringService $firing) {}

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $trigger = Trigger::query()
            ->where('token', $token)
            ->where('type', 'webhook')
            ->where('is_active', true)
            ->first();

        if ($trigger === null) {
            return ApiResponse::notFound();
        }

        // A 200 (not an error) so providers sending mixed event streams don't retry.
        if (! $this->firing->matchesFilters($trigger, $request->all(), $request->headers->all())) {
            return ApiResponse::success(null, 'Event ignored by trigger filters.');
        }

        $run = $this->firing->fire($trigger, $request->all(), 'webhook');

        if ($run === null) {
            return ApiResponse::error('This trigger is not currently runnable.', Response::HTTP_CONFLICT);
        }

        return ApiResponse::success(['run_id' => $run->id], 'Run started.', Response::HTTP_ACCEPTED);
    }
}
