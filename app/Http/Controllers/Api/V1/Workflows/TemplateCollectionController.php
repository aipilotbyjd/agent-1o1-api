<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Workflows\TemplateCollectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\TemplateCollection;
use Illuminate\Http\JsonResponse;

class TemplateCollectionController extends Controller
{
    public function index(): JsonResponse
    {
        $collections = TemplateCollection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(TemplateCollectionResource::collection($collections));
    }

    public function show(string $key): JsonResponse
    {
        $collection = TemplateCollection::query()
            ->where('id', $key)
            ->orWhere('slug', $key)
            ->firstOrFail();

        return ApiResponse::success(new TemplateCollectionResource($collection, withTemplates: true));
    }
}
