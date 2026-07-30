<?php

namespace App\Services\Workflows;

/**
 * Runs a workspace-authored regular expression without letting it escape its delimiter.
 *
 * The patterns come from eval assertions and workflow conditions — user input either
 * way. Quoting them into a fixed delimiter means an author cannot close it early and
 * set their own modifiers (`/u`, or a catastrophic-backtracking flag), and an invalid
 * pattern is a failed match rather than a warning and a broken run.
 */
final class SafePattern
{
    public static function matches(string $pattern, string $subject, bool $caseInsensitive = false): bool
    {
        $delimited = '/'.str_replace('/', '\/', $pattern).'/'.($caseInsensitive ? 'i' : '');

        return @preg_match($delimited, $subject) === 1;
    }
}
