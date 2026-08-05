<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerIntake;
use App\Services\Triggers\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public webhook endpoint.
 *
 * This controller only ever verifies, stores, and acknowledges — it never starts
 * a run. That is what keeps the response time flat regardless of how heavy the
 * work behind the trigger is, and it is why providers see a prompt 2xx instead of
 * timing out and disabling the hook.
 *
 * There is no lock here any more: dedupe is enforced by a unique index inside
 * {@see TriggerIntake}, which is both race-free and free of the multi-second
 * block the previous cache lock could impose on a legitimate delivery.
 */
class WebhookController extends Controller
{
    public function __construct(
        public TriggerIntake $intake,
        public WebhookSignatureVerifier $signatures,
    ) {}

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $trigger = Trigger::query()
            ->with(['triggerType', 'triggerable'])
            ->where('token', $token)
            ->where('type', 'webhook')
            ->where('is_active', true)
            ->first();

        if ($trigger === null) {
            return ApiResponse::notFound();
        }

        if (! $this->signatures->verify($trigger, $request)) {
            $this->intake->reject($trigger, 'webhook', $request, 'Invalid signature');

            return ApiResponse::error('Invalid webhook signature.', Response::HTTP_UNAUTHORIZED);
        }

        $result = $this->intake->accept($trigger, 'webhook', $request->all(), $request);

        return $this->respondTo($result->outcome, $result->event->id);
    }

    /**
     * Map the intake outcome onto a response.
     *
     * Filtered and duplicate deliveries answer 200 rather than an error: providers
     * fan every event in a stream at one URL, and answering 4xx to the ones this
     * trigger does not want makes them retry — and eventually disable the hook.
     */
    private function respondTo(TriggerEventStatus $status, int $eventId): JsonResponse
    {
        return match ($status) {
            TriggerEventStatus::Duplicate => ApiResponse::success(['event_id' => $eventId], 'Duplicate delivery ignored.'),
            TriggerEventStatus::Filtered => ApiResponse::success(['event_id' => $eventId], 'Event ignored by trigger filters.'),
            TriggerEventStatus::Skipped => ApiResponse::error('This trigger is not currently runnable.', Response::HTTP_CONFLICT),
            default => ApiResponse::success(['event_id' => $eventId], 'Event accepted.', Response::HTTP_ACCEPTED),
        };
    }
}
