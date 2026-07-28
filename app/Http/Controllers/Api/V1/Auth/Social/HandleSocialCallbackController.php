<?php

namespace App\Http\Controllers\Api\V1\Auth\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SocialCallbackRequest;
use App\Http\Resources\V1\Auth\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class HandleSocialCallbackController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(SocialCallbackRequest $request, string $provider): JsonResponse
    {
        $result = $this->authService->handleSocialCallback($provider);

        return ApiResponse::success([
            'access_token' => $result['access_token'],
            'user' => new UserResource($result['user']),
        ], 'Logged in successfully');
    }
}
