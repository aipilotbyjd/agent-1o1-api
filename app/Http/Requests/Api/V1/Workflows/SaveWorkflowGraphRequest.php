<?php

namespace App\Http\Requests\Api\V1\Workflows;

use App\Enums\WorkflowStepType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWorkflowGraphRequest extends FormRequest
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
        $workspace = $this->route('workspace');

        return [
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_\-]*$/', 'distinct'],
            'steps.*.type' => ['required', Rule::enum(WorkflowStepType::class)],
            'steps.*.config' => ['sometimes', 'array'],
            'steps.*.config.agent_id' => [
                'required_if:steps.*.type,agent',
                Rule::exists('agents', 'id')->where('workspace_id', $workspace->id),
            ],
            'steps.*.config.tool_id' => [
                'required_if:steps.*.type,tool',
                Rule::exists('tools', 'id')->where('workspace_id', $workspace->id),
            ],
            'steps.*.config.field' => ['required_if:steps.*.type,condition', 'string'],
            'steps.*.config.operator' => ['sometimes', Rule::in(['equals', 'not_equals', 'contains', 'gt', 'gte', 'lt', 'lte', 'truthy'])],
            'steps.*.config.mapping' => ['required_if:steps.*.type,transform', 'array'],
            'steps.*.config.seconds' => ['required_if:steps.*.type,delay', 'integer', 'min:1', 'max:86400'],
            'steps.*.config.message' => ['sometimes', 'string', 'max:1000'],
            'steps.*.position' => ['sometimes', 'array'],
            'edges' => ['present', 'array'],
            'edges.*.from' => ['required', 'string'],
            'edges.*.to' => ['required', 'string'],
            'edges.*.condition' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $keys = collect($this->input('steps', []))->pluck('key')->all();

                foreach ($this->input('edges', []) as $index => $edge) {
                    foreach (['from', 'to'] as $end) {
                        if (isset($edge[$end]) && ! in_array($edge[$end], $keys, true)) {
                            $validator->errors()->add("edges.{$index}.{$end}", "Edge references unknown step key [{$edge[$end]}].");
                        }
                    }

                    if (isset($edge['from'], $edge['to']) && $edge['from'] === $edge['to']) {
                        $validator->errors()->add("edges.{$index}.to", 'An edge cannot point to its own step.');
                    }
                }
            },
        ];
    }
}
