<?php

namespace App\Http\Controllers\Api\V1\Auth\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdateUserRequest;
use App\Http\Resources\V1\Auth\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateUserController extends Controller
{
    public function __invoke(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $emailChanged = $request->has('email') && $request->string('email')->value() !== $user->email;

        $user->fill($request->safe()->only(['name', 'email']));

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return ApiResponse::success(new UserResource($user), 'Profile updated successfully');
    }
}
