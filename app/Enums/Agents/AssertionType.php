<?php

namespace App\Enums\Agents;

use App\Services\Workflows\SafePattern;

/**
 * The checks an eval case can make against an agent's response.
 *
 * The list used to be a plain array constant next to a `match` that evaluated it, so
 * adding a type meant remembering to touch both. Here the two cannot drift.
 */
enum AssertionType: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Equals = 'equals';
    case Regex = 'regex';
    case LlmRubric = 'llm_rubric';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether this assertion is graded by the rubric judge rather than deterministically.
     */
    public function needsJudge(): bool
    {
        return $this === self::LlmRubric;
    }

    /**
     * Check the output, returning a failure message or null on pass. Rubric assertions
     * are not evaluated here — they need a model call, so the service owns those.
     */
    public function check(string $output, string $expected): ?string
    {
        $lower = fn (string $text): string => mb_strtolower($text);

        return match ($this) {
            self::Contains => str_contains($lower($output), $lower($expected))
                ? null
                : "Expected output to contain \"{$expected}\".",
            self::NotContains => ! str_contains($lower($output), $lower($expected))
                ? null
                : "Expected output NOT to contain \"{$expected}\".",
            self::Equals => trim($lower($output)) === trim($lower($expected))
                ? null
                : "Expected output to equal \"{$expected}\".",
            self::Regex => SafePattern::matches($expected, $output, caseInsensitive: true)
                ? null
                : "Expected output to match /{$expected}/.",
            self::LlmRubric => null,
        };
    }
}
