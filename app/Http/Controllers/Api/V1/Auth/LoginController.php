<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email')->value(),
            $request->string('password')->value(),
        );

        if (isset($result['two_factor_challenge'])) {
            return ApiResponse::success(
                ['challenge_token' => $result['two_factor_challenge']],
                'Two-factor authentication code required',
            );
        }

        return ApiResponse::success([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
            'user' => new UserResource($result['user']),
        ], 'Logged in successfully');
    }
}
