<?php

namespace App\Http\Controllers\Api\V1\Auth\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\DisableTwoFactorRequest;
use App\Http\Responses\ApiResponse;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;

class DisableTwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactorAuthService) {}

    public function __invoke(DisableTwoFactorRequest $request): JsonResponse
    {
        $this->twoFactorAuthService->disable($request->user());

        return ApiResponse::success(message: 'Two-factor authentication disabled');
    }
}
