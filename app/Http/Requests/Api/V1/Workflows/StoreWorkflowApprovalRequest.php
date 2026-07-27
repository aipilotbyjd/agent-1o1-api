<?php

namespace App\Http\Requests\Api\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowApprovalRequest extends FormRequest
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
            'version_id' => [
                'nullable', 'integer',
                Rule::exists('workflow_versions', 'id')->where('workflow_id', $this->route('workflow')?->id),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
