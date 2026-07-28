<?php

namespace App\Http\Requests\Api\V1\Triggers;

use App\Enums\Workspaces\Permission;
use App\Http\Requests\Api\V1\Triggers\Concerns\ValidatesTriggerTypeFields;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTriggerRequest extends FormRequest
{
    use ValidatesTriggerTypeFields;

    public function authorize(): bool
    {
        return $this->user()->can(Permission::TriggerManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'config' => ['sometimes', 'array'],
            'config.cron' => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! CronExpression::isValidExpression((string) $value)) {
                        $fail('The cron expression is invalid.');
                    }
                },
            ],
            'config.message' => ['sometimes', 'string', 'max:2000'],
            'config.filters' => ['sometimes', 'array'],
            'config.filters.*.source' => ['required', Rule::in(['payload', 'header'])],
            'config.filters.*.path' => ['required', 'string', 'max:255'],
            'config.filters.*.operator' => ['sometimes', Rule::in(['equals', 'not_equals', 'contains'])],
            'config.filters.*.value' => ['required', 'string', 'max:500'],
            'signing_secret' => ['sometimes', 'nullable', 'string', 'max:500'],
            'credential_id' => [
                'sometimes', 'nullable',
                Rule::exists('credentials', 'id')->where('workspace_id', $this->route('workspace')?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $trigger = $this->route('trigger');
                $type = $trigger?->triggerType;

                if ($type === null) {
                    return;
                }

                $this->validateTriggerTypeFields($validator, $type);
            },
        ];
    }
}
