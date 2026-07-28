<?php

namespace App\Http\Requests\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreStickyNoteRequest extends FormRequest
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
            'content' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'position_x' => ['sometimes', 'numeric'],
            'position_y' => ['sometimes', 'numeric'],
            'width' => ['sometimes', 'numeric', 'min:1'],
            'height' => ['sometimes', 'numeric', 'min:1'],
            'z_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
