<?php

namespace App\Http\Requests\Api\V1\Nodes;

use App\Enums\Workflows\WorkflowStepType;
use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::NodeManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('node_categories', 'id')],
            'step_type' => ['required', 'string', Rule::enum(WorkflowStepType::class)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'icon' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:20'],
            'config_schema' => ['required', 'array'],
            'input_schema' => ['sometimes', 'array'],
            'output_schema' => ['sometimes', 'array'],
            'credential_type' => ['sometimes', 'string', 'max:100'],
            'cost_hint_usd' => ['sometimes', 'numeric', 'min:0'],
            'latency_hint_ms' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'docs_url' => ['sometimes', 'url', 'max:500'],
        ];
    }
}
