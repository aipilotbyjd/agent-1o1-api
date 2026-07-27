<?php

namespace App\Http\Requests\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::EnvironmentManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9-]*$/',
                Rule::unique('workspace_environments')->where('workspace_id', $this->route('workspace')?->id),
            ],
            'variables' => ['sometimes', 'nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
