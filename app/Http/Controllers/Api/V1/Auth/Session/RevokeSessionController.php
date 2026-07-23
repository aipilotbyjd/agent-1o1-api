<?php

namespace App\Http\Controllers\Api\V1\Auth\Session;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevokeSessionController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $this->authService->revokeSession($request->user(), $id);

        return ApiResponse::success(message: 'Session revoked successfully');
    }
}
