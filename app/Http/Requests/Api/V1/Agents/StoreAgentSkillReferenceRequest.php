<?php

namespace App\Http\Requests\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentSkillReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::AgentSkillReferenceManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
