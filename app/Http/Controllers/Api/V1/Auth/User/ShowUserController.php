<?php

namespace App\Http\Controllers\Api\V1\Auth\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Auth\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }
}
