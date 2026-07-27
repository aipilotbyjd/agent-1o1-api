<?php

namespace App\Http\Requests\Api\V1\Credentials;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCredentialRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'data' => ['sometimes', 'array'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
