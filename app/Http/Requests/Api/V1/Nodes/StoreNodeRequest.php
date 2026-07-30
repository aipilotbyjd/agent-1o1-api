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
            // A custom node's schema is enforced against real step configs at save time,
            // so it has to be the same object shape the builtin catalog uses.
            'config_schema' => ['required', 'array'],
            'config_schema.type' => ['required', 'string', Rule::in(['object'])],
            // "present" rather than "required": a node with no config fields is valid,
            // and an empty array would fail a required check.
            'config_schema.properties' => ['present', 'array'],
            'config_schema.required' => ['sometimes', 'array'],
            'config_schema.required.*' => ['string'],
            // The stored call this node makes. Its `config_schema` fields become the
            // call's arguments, whether it is run from a workflow step or by an agent.
            'config' => ['required', 'array'],
            'config.url' => ['required', 'url', 'max:2000'],
            'config.method' => ['sometimes', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'config.headers' => ['sometimes', 'array'],
            'config.timeout' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'input_schema' => ['sometimes', 'array'],
            'output_schema' => ['sometimes', 'array'],
            'credential_type' => ['sometimes', 'string', 'max:100', Rule::exists('credential_types', 'key')],
            'credential_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('credentials', 'id')->where('workspace_id', $this->route('workspace')->id),
            ],
            'cost_hint_usd' => ['sometimes', 'numeric', 'min:0'],
            'latency_hint_ms' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'docs_url' => ['sometimes', 'url', 'max:500'],
        ];
    }
}
