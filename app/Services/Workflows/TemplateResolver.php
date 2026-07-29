<?php

namespace App\Services\Workflows;

use InvalidArgumentException;

class TemplateResolver
{
    /**
     * A single `{{ path | filter }}` placeholder, capturing the path and the filter chain.
     */
    private const PLACEHOLDER = '/\{\{\s*([\w.\-]+)\s*((?:\|\s*\w+(?::[^|}]*)?\s*)*)\}\}/';

    /**
     * The same placeholder anchored to both ends, so a template that is *only* a
     * placeholder can be detected and resolved without stringifying its value.
     */
    private const WHOLE_PLACEHOLDER = '/^\s*\{\{\s*([\w.\-]+)\s*((?:\|\s*\w+(?::[^|}]*)?\s*)*)\}\}\s*$/';

    /**
     * Resolve a template to a string, interpolating every placeholder it contains.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $template, array $context): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $matches): string => $this->stringify(
                $this->valueFor($matches[1], $matches[2] ?? '', $context),
            ),
            $template,
        );
    }

    /**
     * Resolve a template, preserving the referenced value's type when the template is a
     * single placeholder and nothing else. `{{ input.count }}` yields the integer 5, while
     * `n={{ input.count }}` yields the string "n=5" — so a step can build a JSON payload
     * with real numbers, booleans, and nested structures rather than stringified ones.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolveValue(string $template, array $context): mixed
    {
        if (preg_match(self::WHOLE_PLACEHOLDER, $template, $matches) === 1) {
            return $this->valueFor($matches[1], $matches[2] ?? '', $context);
        }

        return $this->resolve($template, $context);
    }

    /**
     * Recursively resolve every string in a nested array of templates, leaving
     * non-string leaves (numbers, booleans, null) exactly as they are.
     *
     * @param  array<array-key, mixed>  $templates
     * @param  array<string, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function resolveArray(array $templates, array $context): array
    {
        $resolved = [];

        foreach ($templates as $key => $value) {
            $resolved[$key] = match (true) {
                is_array($value) => $this->resolveArray($value, $context),
                is_string($value) => $this->resolveValue($value, $context),
                default => $value,
            };
        }

        return $resolved;
    }

    /**
     * Look up a dot path in the context and pipe it through the placeholder's filters.
     *
     * @param  array<string, mixed>  $context
     */
    private function valueFor(string $path, string $filterChain, array $context): mixed
    {
        $value = data_get($context, $path);

        foreach ($this->parseFilters($filterChain) as [$filter, $argument]) {
            $value = $this->applyFilter($filter, $argument, $value);
        }

        return $value;
    }

    /**
     * @return array<int, array{0: string, 1: string|null}>
     */
    private function parseFilters(string $filterChain): array
    {
        if (trim($filterChain) === '') {
            return [];
        }

        $filters = [];

        foreach (array_filter(array_map('trim', explode('|', $filterChain))) as $segment) {
            [$name, $argument] = array_pad(explode(':', $segment, 2), 2, null);
            $filters[] = [trim((string) $name), $argument === null ? null : trim($argument)];
        }

        return $filters;
    }

    private function applyFilter(string $filter, ?string $argument, mixed $value): mixed
    {
        return match ($filter) {
            'default' => $value === null || $value === '' ? $argument : $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_encode($value),
            'upper' => mb_strtoupper($this->stringify($value)),
            'lower' => mb_strtolower($this->stringify($value)),
            'trim' => trim($this->stringify($value)),
            default => throw new InvalidArgumentException("Unknown template filter [{$filter}]."),
        };
    }

    /**
     * Render a value for interpolation into surrounding text. Null becomes an empty
     * string rather than the word "null", so a missing path leaves a clean gap.
     */
    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }
}
