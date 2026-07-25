<?php

namespace App\Services\Workflows;

class TemplateResolver
{
    /**
     * Replace {{ dot.path }} placeholders with values from the context.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*([\w.\-]+)\s*\}\}/',
            function (array $matches) use ($context): string {
                $value = data_get($context, $matches[1]);

                return is_scalar($value) ? (string) $value : json_encode($value);
            },
            $template,
        );
    }

    /**
     * Resolve every string value in an array of templates.
     *
     * @param  array<string, mixed>  $templates
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function resolveArray(array $templates, array $context): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) ? $this->resolve($value, $context) : $value,
            $templates,
        );
    }
}
