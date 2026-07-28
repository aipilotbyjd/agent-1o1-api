<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\SubscriptionCheckoutRequest;
use App\Http\Resources\V1\Billing\SubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::BillingView);

        $subscription = $workspace->subscription('default')?->load('plan');

        return ApiResponse::success($subscription ? new SubscriptionResource($subscription) : null);
    }

    public function checkout(SubscriptionCheckoutRequest $request, Workspace $workspace): JsonResponse
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));
        $interval = BillingInterval::from($request->validated('interval'));

        if ($workspace->subscription('default') !== null) {
            $subscription = $this->subscriptionService->swap($workspace, $plan, $interval);

            return ApiResponse::success(
                new SubscriptionResource($subscription->load('plan')),
                'Subscription updated.',
            );
        }

        $checkoutUrl = $this->subscriptionService->checkout($workspace, $plan, $interval);

        return ApiResponse::success(['checkout_url' => $checkoutUrl], 'Checkout session created.');
    }

    public function cancel(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::BillingManage);

        $subscription = $this->subscriptionService->cancel($workspace);

        return ApiResponse::success(new SubscriptionResource($subscription->load('plan')), 'Subscription canceled.');
    }

    public function resume(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::BillingManage);

        $subscription = $this->subscriptionService->resume($workspace);

        return ApiResponse::success(new SubscriptionResource($subscription->load('plan')), 'Subscription resumed.');
    }

    public function portal(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::BillingManage);

        return ApiResponse::success(['url' => $this->subscriptionService->portalUrl($workspace)]);
    }
}
