<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;
use App\Services\Workflows\SafePattern;
use App\Services\Workflows\TemplateResolver;

class ConditionStepHandler implements StepHandler
{
    public function __construct(public TemplateResolver $templates) {}

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $config = $step['config'] ?? [];

        // The typed value is used for comparisons so a numeric field compares as a
        // number, while the string form backs the text operators.
        $left = $this->templates->resolveValue($config['field'] ?? '', $context);
        $leftString = is_scalar($left) ? (string) $left : (string) json_encode($left);
        $operator = $config['operator'] ?? 'truthy';
        $right = $config['value'] ?? '';
        $rightString = is_scalar($right) ? (string) $right : (string) json_encode($right);

        $result = match ($operator) {
            'equals' => $leftString === $rightString,
            'not_equals' => $leftString !== $rightString,
            'contains' => is_array($left)
                ? in_array($right, $left, false)
                : str_contains($leftString, $rightString),
            'not_contains' => is_array($left)
                ? ! in_array($right, $left, false)
                : ! str_contains($leftString, $rightString),
            'starts_with' => str_starts_with($leftString, $rightString),
            'ends_with' => str_ends_with($leftString, $rightString),
            'matches' => SafePattern::matches($rightString, $leftString),
            'in' => in_array($leftString, $this->listFrom($right), true),
            'not_in' => ! in_array($leftString, $this->listFrom($right), true),
            'exists' => $left !== null,
            'missing' => $left === null,
            'empty' => $left === null || $left === '' || $left === [],
            'not_empty' => ! ($left === null || $left === '' || $left === []),
            'gt' => $this->compareNumeric($left, $right, fn (float $a, float $b): bool => $a > $b),
            'gte' => $this->compareNumeric($left, $right, fn (float $a, float $b): bool => $a >= $b),
            'lt' => $this->compareNumeric($left, $right, fn (float $a, float $b): bool => $a < $b),
            'lte' => $this->compareNumeric($left, $right, fn (float $a, float $b): bool => $a <= $b),
            default => $this->truthy($left),
        };

        return ['output' => ['result' => $result ? 'true' : 'false']];
    }

    private function truthy(mixed $value): bool
    {
        return match (true) {
            $value === null => false,
            is_bool($value) => $value,
            is_array($value) => $value !== [],
            is_numeric($value) => (float) $value !== 0.0,
            default => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        };
    }

    /**
     * Numeric comparisons require both sides to actually be numbers — comparing a
     * non-numeric string as a float would silently read it as zero.
     */
    private function compareNumeric(mixed $left, mixed $right, callable $comparator): bool
    {
        if (! is_numeric($left) || ! is_numeric($right)) {
            return false;
        }

        return $comparator((float) $left, (float) $right);
    }

    /**
     * @return array<int, string>
     */
    private function listFrom(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): string => (string) $item, $value);
        }

        return array_map('trim', explode(',', (string) $value));
    }
}
