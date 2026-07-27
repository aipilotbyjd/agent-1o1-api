<?php

namespace App\Http\Requests\Api\V1\Credentials;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCredentialRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::exists('credential_types', 'key')->where('is_active', true)],
            'data' => ['required', 'array'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
