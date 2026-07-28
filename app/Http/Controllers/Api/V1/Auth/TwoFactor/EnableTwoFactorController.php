<?php

namespace App\Http\Controllers\Api\V1\Auth\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnableTwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactorAuthService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->twoFactorAuthService->enable($request->user());

        return ApiResponse::success(
            $result,
            'Scan the QR code with your authenticator app, then confirm with a code to finish enabling two-factor authentication.',
        );
    }
}
