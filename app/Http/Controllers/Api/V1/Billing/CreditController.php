<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Billing\UsagePeriodResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::BillingView);

        $period = $workspace->currentUsagePeriod();

        return ApiResponse::success($period ? new UsagePeriodResource($period) : null);
    }
}
