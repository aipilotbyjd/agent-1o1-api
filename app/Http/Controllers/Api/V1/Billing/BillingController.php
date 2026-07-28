<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\PackCheckoutRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly PackService $packService) {}

    public function packCatalog(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::BillingView);

        return ApiResponse::success($this->packService->catalog());
    }

    public function packCheckout(PackCheckoutRequest $request, Workspace $workspace): JsonResponse
    {
        $checkoutUrl = $this->packService->checkout($workspace, $request->validated('pack_key'), $request->user());

        return ApiResponse::success(['checkout_url' => $checkoutUrl], 'Pack checkout session created.');
    }
}
