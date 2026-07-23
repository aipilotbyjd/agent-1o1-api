<?php

namespace App\Http\Controllers\Api\V1\Auth\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class VerifyTwoFactorController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(VerifyTwoFactorRequest $request): JsonResponse
    {
        $result = $this->authService->completeTwoFactorChallenge(
            $request->string('challenge_token')->value(),
            $request->string('code')->value(),
        );

        return ApiResponse::success([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
            'user' => new UserResource($result['user']),
        ], 'Logged in successfully');
    }
}
