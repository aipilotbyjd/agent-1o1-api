<?php

namespace App\Http\Requests\Api\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowEnvironmentReleaseRequest extends FormRequest
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
        $workspace = $this->route('workspace');
        $workflow = $this->route('workflow');

        return [
            'environment_id' => [
                'required', 'integer',
                Rule::exists('workspace_environments', 'id')->where('workspace_id', $workspace?->id),
            ],
            'version_id' => [
                'required', 'integer',
                Rule::exists('workflow_versions', 'id')->where('workflow_id', $workflow?->id),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
