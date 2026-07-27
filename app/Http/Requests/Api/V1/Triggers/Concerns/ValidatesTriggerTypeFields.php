<?php

namespace App\Http\Requests\Api\V1\Triggers\Concerns;

use App\Models\Triggers\TriggerType;
use Illuminate\Validation\Validator;

trait ValidatesTriggerTypeFields
{
    /**
     * Enforce TriggerType.fields (name/label/type/required) against the
     * submitted config — the catalog entry's field list is otherwise only
     * decorative UI hints, so this is what actually requires them.
     */
    private function validateTriggerTypeFields(Validator $validator, ?TriggerType $type): void
    {
        if ($type === null) {
            return;
        }

        foreach ($type->fields ?? [] as $field) {
            $name = $field['name'] ?? null;

            if ($name === null) {
                continue;
            }

            $value = data_get($this->input('config'), $name);

            if (($field['required'] ?? false) && $value === null) {
                $validator->errors()->add("config.{$name}", "The {$field['label']} field is required.");

                continue;
            }

            if ($value === null) {
                continue;
            }

            $matchesType = match ($field['type'] ?? 'string') {
                'number' => is_numeric($value),
                'boolean' => is_bool($value) || in_array($value, ['true', 'false'], true),
                default => true,
            };

            if (! $matchesType) {
                $validator->errors()->add("config.{$name}", "The {$field['label']} field must be a {$field['type']}.");
            }
        }
    }
}
