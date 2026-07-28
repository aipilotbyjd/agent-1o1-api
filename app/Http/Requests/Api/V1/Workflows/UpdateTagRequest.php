<?php

namespace App\Http\Requests\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
        ];
    }
}
