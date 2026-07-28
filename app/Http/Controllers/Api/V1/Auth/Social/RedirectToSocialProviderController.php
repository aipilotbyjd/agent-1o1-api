<?php

namespace App\Http\Controllers\Api\V1\Auth\Social;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class RedirectToSocialProviderController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(string $provider): JsonResponse
    {
        return ApiResponse::success(['url' => $this->authService->socialRedirectUrl($provider)]);
    }
}
