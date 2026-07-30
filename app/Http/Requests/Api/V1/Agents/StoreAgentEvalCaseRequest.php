<?php

namespace App\Http\Requests\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Services\Agents\AgentEvalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentEvalCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::AgentEvalManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'input' => ['required', 'string'],
            'assertions' => ['required', 'array', 'min:1'],
            'assertions.*.type' => ['required', 'string', Rule::in(AgentEvalService::assertionTypes())],
            'assertions.*.value' => ['required', 'string'],
        ];
    }
}
