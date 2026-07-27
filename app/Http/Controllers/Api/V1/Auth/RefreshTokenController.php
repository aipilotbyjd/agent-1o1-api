<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class RefreshTokenController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->authService->refresh($request->string('refresh_token')->value());

        return ApiResponse::success([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
        ], 'Token refreshed successfully');
    }
}
