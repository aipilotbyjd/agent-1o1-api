<?php

namespace App\Services\Workflows\Nodes;

class ConfigSchemaValidator
{
    /**
     * Check a step's config against its node's config_schema, returning human-readable
     * issues. Deliberately covers only the subset of JSON Schema the catalog actually
     * uses — required keys and property types — rather than pulling in a full validator.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    public function issues(array $schema, array $config, string $stepKey): array
    {
        $issues = [];

        foreach ($schema['required'] ?? [] as $required) {
            if (! array_key_exists($required, $config) || $config[$required] === null || $config[$required] === '') {
                $issues[] = "Step [{$stepKey}] is missing required config field [{$required}].";
            }
        }

        foreach ($schema['properties'] ?? [] as $property => $rules) {
            if (! array_key_exists($property, $config) || $config[$property] === null) {
                continue;
            }

            $expected = $rules['type'] ?? null;

            if ($expected !== null && ! $this->matchesType($config[$property], $expected)) {
                $actual = $this->describe($config[$property]);
                $issues[] = "Step [{$stepKey}] config field [{$property}] must be of type {$expected}, {$actual} given.";
            }
        }

        return $issues;
    }

    private function matchesType(mixed $value, string $expected): bool
    {
        return match ($expected) {
            // A config field is often a template string that only resolves to its real
            // type at run time, so a template is accepted wherever a scalar is expected.
            'integer' => is_int($value) || $this->isTemplate($value),
            'number' => is_int($value) || is_float($value) || $this->isTemplate($value),
            'boolean' => is_bool($value) || $this->isTemplate($value),
            'string' => is_string($value),
            'object' => is_array($value),
            'array' => is_array($value),
            default => true,
        };
    }

    private function isTemplate(mixed $value): bool
    {
        return is_string($value) && str_contains($value, '{{');
    }

    private function describe(mixed $value): string
    {
        return match (true) {
            is_array($value) => 'array',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            default => get_debug_type($value),
        };
    }
}
