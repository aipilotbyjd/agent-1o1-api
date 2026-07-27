<?php

namespace App\Http\Requests\Api\V1\Variables;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariableRequest extends FormRequest
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
            'value' => ['sometimes', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ];
    }
}
