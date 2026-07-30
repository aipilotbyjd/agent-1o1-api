<?php

namespace App\Http\Requests\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Services\Agents\AgentEvalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentEvalSuiteRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cases' => ['sometimes', 'array'],
            'cases.*.name' => ['required_with:cases', 'string', 'max:255'],
            'cases.*.input' => ['required_with:cases', 'string'],
            'cases.*.assertions' => ['required_with:cases', 'array', 'min:1'],
            'cases.*.assertions.*.type' => ['required', 'string', Rule::in(AgentEvalService::assertionTypes())],
            'cases.*.assertions.*.value' => ['required', 'string'],
        ];
    }
}
