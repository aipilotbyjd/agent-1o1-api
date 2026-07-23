<?php

namespace App\Http\Controllers\Api\V1\Auth\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegenerateRecoveryCodesController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactorAuthService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $recoveryCodes = $this->twoFactorAuthService->regenerateRecoveryCodes($request->user());

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'Recovery codes regenerated. Store these somewhere safe — they will not be shown again.',
        );
    }
}
