<?php

namespace App\Http\Requests\Api\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentSkillScriptRequest extends FormRequest
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
            'description' => ['required', 'string'],
            'language' => ['sometimes', 'string', 'max:20'],
            'code' => ['required', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
