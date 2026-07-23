<?php

namespace App\Http\Controllers\Api\V1\Auth\Session;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SessionResource;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndexSessionController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success(SessionResource::collection($this->authService->sessions($request->user())));
    }
}
