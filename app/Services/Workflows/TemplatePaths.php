<?php

namespace App\Services\Workflows;

/**
 * Finds the template paths mentioned anywhere inside a step's config.
 *
 * Config is an arbitrarily nested array of strings, so both callers reach for the same
 * trick — encode it to JSON and scan the text — rather than walking it twice with
 * different recursion. Keeping the two patterns in one place stops them drifting: the
 * engine uses this to decide which secrets a step may see, and the dry runner uses it to
 * warn about paths nothing provides.
 */
final class TemplatePaths
{
    /**
     * Any `{{ path }}` placeholder's leading dot path.
     */
    private const PLACEHOLDER_PATH = '/\{\{\s*([\w.\-]+)/';

    /**
     * A `variables.x` reference, wherever it appears — including inside a longer
     * expression, which the placeholder pattern alone would miss.
     */
    private const VARIABLE_PATH = '/variables\.([\w\-]+)/';

    /**
     * Every `variables.x` key named in the config.
     *
     * @param  array<array-key, mixed>  $config
     * @return array<int, string>
     */
    public static function variableKeysIn(array $config): array
    {
        return self::matchAll(self::VARIABLE_PATH, $config);
    }

    /**
     * Every dot path the config interpolates.
     *
     * @param  array<array-key, mixed>  $config
     * @return array<int, string>
     */
    public static function referencedIn(array $config): array
    {
        return self::matchAll(self::PLACEHOLDER_PATH, $config);
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<int, string>
     */
    private static function matchAll(string $pattern, array $config): array
    {
        $encoded = json_encode($config);

        if ($encoded === false) {
            return [];
        }

        preg_match_all($pattern, $encoded, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
