<?php

namespace App\Http\Controllers\Api\V1\Auth\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecoveryCodesController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $twoFactorAuthService) {}

    /**
     * Note: recovery codes are hashed at rest, so this only reflects how many remain
     * (as opaque placeholders), not their plaintext values — those were only ever shown
     * once, at confirm/regenerate time.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $remaining = count($this->twoFactorAuthService->recoveryCodes($request->user()));

        return ApiResponse::success(['recovery_codes_remaining' => $remaining]);
    }
}
