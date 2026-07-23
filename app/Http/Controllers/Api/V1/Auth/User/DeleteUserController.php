<?php

namespace App\Http\Controllers\Api\V1\Auth\User;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\RefreshToken;

class DeleteUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokenIds = $user->tokens()->pluck('id');
        RefreshToken::whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        $user->tokens()->update(['revoked' => true]);

        $user->delete();

        return ApiResponse::success(message: 'Account deleted successfully');
    }
}
