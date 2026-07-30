<?php

namespace App\Http\Requests\Api\V1\Nodes;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNodeRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'color' => ['sometimes', 'string', 'max:20'],
            'config_schema' => ['sometimes', 'array'],
            'config' => ['sometimes', 'array'],
            'config.url' => ['sometimes', 'url', 'max:2000'],
            'config.method' => ['sometimes', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'config.headers' => ['sometimes', 'array'],
            'config.timeout' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'input_schema' => ['sometimes', 'array'],
            'output_schema' => ['sometimes', 'array'],
            'credential_type' => ['sometimes', 'string', 'max:100'],
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
