<?php

namespace App\Http\Controllers\Api\V1\Auth\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ConfirmTwoFactorRequest;
use App\Http\Responses\ApiResponse;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;

class ConfirmTwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactorAuthService) {}

    public function __invoke(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $recoveryCodes = $this->twoFactorAuthService->confirm(
            $request->user(),
            $request->string('code')->value(),
        );

        return ApiResponse::success(
            ['recovery_codes' => $recoveryCodes],
            'Two-factor authentication enabled. Store these recovery codes somewhere safe — they will not be shown again.',
        );
    }
}
