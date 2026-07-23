<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Route is protected by the `signed` middleware (tamper-proof URL), not auth:api —
     * the user isn't necessarily logged in when clicking the emailed link.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->route('id'));

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return ApiResponse::forbidden('Invalid verification link');
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(message: 'Email already verified');
        }

        $user->markEmailAsVerified();

        return ApiResponse::success(message: 'Email verified successfully');
    }
}
