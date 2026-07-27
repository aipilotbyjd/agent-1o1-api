<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->string('email')->value());

        return ApiResponse::success(message: 'If that email exists, a password reset link has been sent');
    }
}
