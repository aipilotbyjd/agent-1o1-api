<?php

namespace App\Http\Requests\Api\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StoreRunReplayPackRequest extends FormRequest
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
            'run_id' => ['sometimes', 'nullable', 'integer'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'version_snapshot' => ['required', 'array'],
            'trigger_data' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
