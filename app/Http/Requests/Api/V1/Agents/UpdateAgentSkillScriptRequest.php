<?php

namespace App\Http\Requests\Api\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentSkillScriptRequest extends FormRequest
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
            'description' => ['sometimes', 'string'],
            'language' => ['sometimes', 'string', 'max:20'],
            'code' => ['sometimes', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
