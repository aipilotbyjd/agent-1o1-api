<?php

namespace App\Http\Requests\Api\V1\Tools;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ToolManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(['http'])],
            'config' => ['required', 'array'],
            'config.url' => ['required_if:type,http', 'url', 'max:2000'],
            'config.method' => ['sometimes', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'config.headers' => ['sometimes', 'array'],
            'config.timeout' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'config.parameters' => ['sometimes', 'array'],
            'config.parameters.*.name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'config.parameters.*.type' => ['sometimes', 'string', Rule::in(['string', 'integer', 'number', 'boolean'])],
            'config.parameters.*.description' => ['sometimes', 'string', 'max:500'],
            'config.parameters.*.required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
