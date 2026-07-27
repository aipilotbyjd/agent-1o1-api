<?php

namespace App\Http\Requests\Api\V1\Triggers;

use App\Http\Requests\Api\V1\Triggers\Concerns\ValidatesTriggerTypeFields;
use App\Models\TriggerType;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTriggerRequest extends FormRequest
{
    use ValidatesTriggerTypeFields;

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
            'type' => ['required_without:trigger_type_key', 'string', Rule::in(['webhook', 'schedule', 'manual', 'polling'])],
            'trigger_type_key' => ['required_without:type', 'string', Rule::exists('trigger_types', 'key')->where('is_active', true)],
            'config' => ['sometimes', 'array'],
            'config.cron' => [
                'required_if:type,schedule',
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
                $key = $this->input('trigger_type_key');

                if ($key === null) {
                    return;
                }

                $type = TriggerType::query()->where('key', $key)->first();

                if ($type === null) {
                    return;
                }

                // A schedule catalog entry without a preset cron needs one from the user.
                if ($type->mechanism === 'schedule') {
                    $cron = $this->input('config.cron') ?? ($type->preset_config['cron'] ?? null);

                    if ($cron === null || ! CronExpression::isValidExpression((string) $cron)) {
                        $validator->errors()->add('config.cron', 'A valid cron expression is required for this trigger type.');
                    }
                }

                if ($type->signature_scheme !== null && $this->input('signing_secret') === null) {
                    $validator->errors()->add('signing_secret', 'A signing secret is required for this trigger type.');
                }

                $this->validateTriggerTypeFields($validator, $type);
            },
        ];
    }
}
