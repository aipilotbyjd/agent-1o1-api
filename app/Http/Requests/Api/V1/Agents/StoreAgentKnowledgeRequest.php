<?php

namespace App\Http\Requests\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::AgentKnowledgeManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'source_type' => ['sometimes', 'string', 'max:20'],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tokens' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
