<?php

namespace App\Http\Requests\Api\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowBuilderSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasWorkspaceRole($this->route('workspace'), 'owner', 'admin', 'member');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workflow_id' => [
                'nullable',
                Rule::exists('workflows', 'id')->where('workspace_id', $this->route('workspace')?->id),
            ],
            'title' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
