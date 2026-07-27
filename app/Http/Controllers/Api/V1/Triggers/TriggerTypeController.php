<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Triggers\TriggerType;
use Illuminate\Http\JsonResponse;

class TriggerTypeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $types = TriggerType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => $group->map(fn (TriggerType $type): array => [
                'key' => $type->key,
                'name' => $type->name,
                'description' => $type->description,
                'mechanism' => $type->mechanism,
                'preset_config' => $type->preset_config,
                'fields' => $type->fields,
            ])->values());

        return ApiResponse::success($types);
    }
}
