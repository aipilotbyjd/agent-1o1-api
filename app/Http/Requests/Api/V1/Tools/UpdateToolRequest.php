<?php

namespace App\Http\Requests\Api\V1\Tools;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasWorkspaceRole($this->route('workspace'), 'owner', 'admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'config' => ['sometimes', 'array'],
            'config.url' => ['sometimes', 'url', 'max:2000'],
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
