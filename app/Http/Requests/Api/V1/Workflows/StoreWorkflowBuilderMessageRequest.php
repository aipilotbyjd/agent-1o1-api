<?php

namespace App\Http\Requests\Api\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowBuilderMessageRequest extends FormRequest
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
            'content' => ['required', 'string'],
        ];
    }
}
