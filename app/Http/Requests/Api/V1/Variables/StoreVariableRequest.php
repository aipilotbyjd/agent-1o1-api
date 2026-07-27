<?php

namespace App\Http\Requests\Api\V1\Variables;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVariableRequest extends FormRequest
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
            'key' => [
                'required', 'string', 'max:100', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('variables')->where('workspace_id', $this->route('workspace')?->id),
            ],
            'value' => ['required', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ];
    }
}
