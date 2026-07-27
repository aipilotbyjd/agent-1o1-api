<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowShare;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ShowSharedWorkflowController extends Controller
{
    public function __invoke(string $token): JsonResponse
    {
        $share = WorkflowShare::query()->where('token', $token)->firstOrFail();

        if ($share->isExpired()) {
            return ApiResponse::error('This share link has expired.', Response::HTTP_GONE);
        }

        $share->recordView();

        return ApiResponse::success([
            'name' => $share->workflow->name,
            'description' => $share->workflow->description,
            'graph' => $share->workflow->currentVersion?->graph,
            'allow_clone' => $share->allow_clone,
        ]);
    }
}
