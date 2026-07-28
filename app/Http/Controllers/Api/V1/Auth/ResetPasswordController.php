<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class ResetPasswordController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->string('email')->value(),
            $request->string('token')->value(),
            $request->string('password')->value(),
        );

        return ApiResponse::success(message: 'Password reset successfully. Please log in again.');
    }
}
