<?php

namespace App\Http\Requests\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('folders', 'id')->where('workspace_id', $workspace->id),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
