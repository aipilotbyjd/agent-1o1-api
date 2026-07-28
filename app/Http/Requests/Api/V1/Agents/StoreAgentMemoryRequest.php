<?php

namespace App\Http\Requests\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::AgentMemoryManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'type' => ['sometimes', 'string', 'max:30'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
