<?php

namespace App\Http\Requests\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::AgentSkillManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('agent_skills')->where('workspace_id', $this->route('workspace')?->id)
                    ->ignore($this->route('skill')),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'instructions' => ['sometimes', 'string'],
            'is_shared' => ['sometimes', 'boolean'],
        ];
    }
}
