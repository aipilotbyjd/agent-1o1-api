<?php

namespace App\Http\Requests\Api\V1\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceEnvironmentRequest extends FormRequest
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
            'variables' => ['sometimes', 'nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
