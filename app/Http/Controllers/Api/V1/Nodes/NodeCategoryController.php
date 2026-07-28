<?php

namespace App\Http\Controllers\Api\V1\Nodes;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Nodes\NodeCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Nodes\NodeCategory;
use Illuminate\Http\JsonResponse;

class NodeCategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $categories = NodeCategory::query()->orderBy('sort_order')->get();

        return ApiResponse::success(NodeCategoryResource::collection($categories));
    }
}
