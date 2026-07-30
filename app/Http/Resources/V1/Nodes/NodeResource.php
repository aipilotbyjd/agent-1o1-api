<?php

namespace App\Http\Resources\V1\Nodes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => new NodeCategoryResource($this->whenLoaded('category')),
            'workspace_id' => $this->workspace_id,
            'step_type' => $this->step_type->value,
            'type' => $this->type,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'config_schema' => $this->config_schema,
            // Only a custom node stores its own call definition; builtins define theirs
            // in code.
            'config' => $this->when($this->is_custom, $this->config),
            'input_schema' => $this->input_schema,
            'output_schema' => $this->output_schema,
            'credential_type' => $this->credential_type,
            'credential_id' => $this->credential_id,
            // Present only when the node was loaded through an agent's attachments.
            'attachment' => $this->whenPivotLoaded('agent_node', fn (): array => [
                'config' => $this->pivot->config,
                'exposed_fields' => $this->pivot->exposed_fields,
            ]),
            'cost_hint_usd' => $this->cost_hint_usd,
            'latency_hint_ms' => $this->latency_hint_ms,
            'is_active' => $this->is_active,
            'is_premium' => $this->is_premium,
            'is_custom' => $this->is_custom,
            'docs_url' => $this->docs_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
