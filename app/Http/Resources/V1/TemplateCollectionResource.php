<?php

namespace App\Http\Resources\V1;

use App\Models\TemplateCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateCollectionResource extends JsonResource
{
    public function __construct(TemplateCollection $resource, private readonly bool $withTemplates = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'template_ids' => $this->template_ids,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'templates' => $this->when(
                $this->withTemplates,
                fn () => WorkflowTemplateResource::collection($this->resource->templates()),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
