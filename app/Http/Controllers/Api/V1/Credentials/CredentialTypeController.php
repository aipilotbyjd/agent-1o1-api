<?php

namespace App\Http\Controllers\Api\V1\Credentials;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CredentialTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\CredentialType;
use Illuminate\Http\JsonResponse;

class CredentialTypeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $types = CredentialType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(CredentialTypeResource::collection($types));
    }
}
