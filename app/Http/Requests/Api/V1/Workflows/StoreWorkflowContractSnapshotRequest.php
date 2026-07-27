<?php

namespace App\Http\Requests\Api\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowContractSnapshotRequest extends FormRequest
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
            'input_schema' => ['sometimes', 'nullable', 'array'],
            'output_schema' => ['sometimes', 'nullable', 'array'],
            'node_signature' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
