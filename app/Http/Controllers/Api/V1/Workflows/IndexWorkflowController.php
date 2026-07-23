<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class IndexWorkflowController extends Controller
{
    /**
     * Placeholder — demonstrates gating a feature behind a verified email via the
     * `verified` middleware. Replace with real workflow listing once that's built.
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([], 'Workflows placeholder — reachable only with a verified email');
    }
}
