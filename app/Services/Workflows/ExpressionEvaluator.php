<?php

namespace App\Services\Workflows;

use App\Exceptions\Workflows\InvalidExpressionException;

/**
 * A small, total expression language for workflow steps.
 *
 * Workspace-authored strings are evaluated by this recursive-descent parser rather than
 * by PHP, so a workflow can compute values without being able to reach the filesystem,
 * the network, or the container. Everything it can do is in this file.
 */
class ExpressionEvaluator
{
    /**
     * @var array<int, array{type: string, value: mixed}>
     */
    private array $tokens = [];

    private int $position = 0;

    /**
     * @var array<string, callable>
     */
    private array $functions;

    public function __construct()
    {
        $this->functions = [
            'length' => fn (mixed $value): int => match (true) {
                is_array($value) => count($value),
                is_string($value) => mb_strlen($value),
                default => 0,
            },
            'upper' => fn (mixed $value): string => mb_strtoupper((string) $value),
            'lower' => fn (mixed $value): string => mb_strtolower((string) $value),
            'trim' => fn (mixed $value): string => trim((string) $value),
            'abs' => fn (mixed $value): int|float => abs((float) $value),
            'round' => fn (mixed $value, mixed $precision = 0): float => round((float) $value, (int) $precision),
            'floor' => fn (mixed $value): float => floor((float) $value),
            'ceil' => fn (mixed $value): float => ceil((float) $value),
            'number' => fn (mixed $value): int|float => is_numeric($value) ? $value + 0 : 0,
            'concat' => fn (mixed ...$values): string => implode('', array_map(
                fn (mixed $value): string => (string) $value,
                $values,
            )),
            'coalesce' => function (mixed ...$values): mixed {
                foreach ($values as $value) {
                    if ($value !== null && $value !== '') {
                        return $value;
                    }
                }

                return null;
            },
            'contains' => fn (mixed $haystack, mixed $needle): bool => is_array($haystack)
                ? in_array($needle, $haystack, false)
                : str_contains((string) $haystack, (string) $needle),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     *
     * @throws InvalidExpressionException
     */
    public function evaluate(string $expression, array $context): mixed
    {
        $this->tokens = $this->tokenize($expression);
        $this->position = 0;

        $value = $this->parseOr($context);

        if ($this->position < count($this->tokens)) {
            throw new InvalidExpressionException("Unexpected token in expression [{$expression}].");
        }

        return $value;
    }

    /**
     * @return array<int, array{type: string, value: mixed}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = mb_strlen($expression);
        $index = 0;

        while ($index < $length) {
            $character = $expression[$index];

            if (ctype_space($character)) {
                $index++;

                continue;
            }

            if (ctype_digit($character)) {
                $number = '';

                while ($index < $length && (ctype_digit($expression[$index]) || $expression[$index] === '.')) {
                    $number .= $expression[$index++];
                }

                $tokens[] = ['type' => 'number', 'value' => str_contains($number, '.') ? (float) $number : (int) $number];

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
                $string = '';
                $index++;

                while ($index < $length && $expression[$index] !== $quote) {
                    $string .= $expression[$index++];
                }

                if ($index >= $length) {
                    throw new InvalidExpressionException('Unterminated string literal in expression.');
                }

                $index++;
                $tokens[] = ['type' => 'string', 'value' => $string];

                continue;
            }

            if (ctype_alpha($character) || $character === '_') {
                $identifier = '';

                while ($index < $length && (ctype_alnum($expression[$index]) || in_array($expression[$index], ['_', '.'], true))) {
                    $identifier .= $expression[$index++];
                }

                $tokens[] = ['type' => 'identifier', 'value' => $identifier];

                continue;
            }

            $twoCharacter = mb_substr($expression, $index, 2);

            if (in_array($twoCharacter, ['==', '!=', '<=', '>=', '&&', '||'], true)) {
                $tokens[] = ['type' => 'operator', 'value' => $twoCharacter];
                $index += 2;

                continue;
            }

            if (in_array($character, ['+', '-', '*', '/', '%', '<', '>', '!', '(', ')', ','], true)) {
                $tokens[] = ['type' => 'operator', 'value' => $character];
                $index++;

                continue;
            }

            throw new InvalidExpressionException("Unexpected character [{$character}] in expression.");
        }

        return $tokens;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parseOr(array $context): mixed
    {
        $left = $this->parseAnd($context);

        while ($this->isOperator('||')) {
            $this->position++;
            $right = $this->parseAnd($context);
            $left = (bool) $left || (bool) $right;
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parseAnd(array $context): mixed
    {
        $left = $this->parseComparison($context);

        while ($this->isOperator('&&')) {
            $this->position++;
            $right = $this->parseComparison($context);
            $left = (bool) $left && (bool) $right;
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parseComparison(array $context): mixed
    {
        $left = $this->parseAdditive($context);

        while ($this->isOperator('==', '!=', '<', '<=', '>', '>=')) {
            $operator = $this->tokens[$this->position]['value'];
            $this->position++;
            $right = $this->parseAdditive($context);

            $left = match ($operator) {
                '==' => $left == $right,
                '!=' => $left != $right,
                '<' => $left < $right,
                '<=' => $left <= $right,
                '>' => $left > $right,
                '>=' => $left >= $right,
                default => throw new InvalidExpressionException("Unknown operator [{$operator}]."),
            };
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parseAdditive(array $context): mixed
    {
        $left = $this->parseMultiplicative($context);

        while ($this->isOperator('+', '-')) {
            $operator = $this->tokens[$this->position]['value'];
            $this->position++;
            $right = $this->parseMultiplicative($context);

            $left = $operator === '+'
                ? $this->add($left, $right)
                : (float) $left - (float) $right;
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parseMultiplicative(array $context): mixed
    {
        $left = $this->parseUnary($context);

        while ($this->isOperator('*', '/', '%')) {
            $operator = $this->tokens[$this->position]['value'];
            $this->position++;
            $right = $this->parseUnary($context);

            if (in_array($operator, ['/', '%'], true) && (float) $right === 0.0) {
                throw new InvalidExpressionException('Division by zero in expression.');
            }

            $left = match ($operator) {
                '*' => (float) $left * (float) $right,
                '/' => (float) $left / (float) $right,
                default => (float) $left % (float) $right,
            };
        }

        return $left;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parseUnary(array $context): mixed
    {
        if ($this->isOperator('!')) {
            $this->position++;

            return ! (bool) $this->parseUnary($context);
        }

        if ($this->isOperator('-')) {
            $this->position++;

            return -1 * (float) $this->parseUnary($context);
        }

        return $this->parsePrimary($context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parsePrimary(array $context): mixed
    {
        $token = $this->tokens[$this->position] ?? throw new InvalidExpressionException('Unexpected end of expression.');

        if ($this->isOperator('(')) {
            $this->position++;
            $value = $this->parseOr($context);

            if (! $this->isOperator(')')) {
                throw new InvalidExpressionException('Missing closing parenthesis in expression.');
            }

            $this->position++;

            return $value;
        }

        if ($token['type'] === 'number' || $token['type'] === 'string') {
            $this->position++;

            return $token['value'];
        }

        if ($token['type'] === 'identifier') {
            $this->position++;
            $name = (string) $token['value'];

            if ($this->isOperator('(')) {
                return $this->callFunction($name, $context);
            }

            return match ($name) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => data_get($context, $name),
            };
        }

        throw new InvalidExpressionException("Unexpected token [{$token['value']}] in expression.");
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function callFunction(string $name, array $context): mixed
    {
        if (! isset($this->functions[$name])) {
            throw new InvalidExpressionException("Unknown function [{$name}] in expression.");
        }

        $this->position++;
        $arguments = [];

        while (! $this->isOperator(')')) {
            $arguments[] = $this->parseOr($context);

            if ($this->isOperator(',')) {
                $this->position++;

                continue;
            }

            if (! $this->isOperator(')')) {
                throw new InvalidExpressionException("Malformed arguments to [{$name}] in expression.");
            }
        }

        $this->position++;

        return ($this->functions[$name])(...$arguments);
    }

    /**
     * `+` concatenates when either side is a non-numeric string, and adds otherwise.
     */
    private function add(mixed $left, mixed $right): mixed
    {
        if ((is_string($left) && ! is_numeric($left)) || (is_string($right) && ! is_numeric($right))) {
            return (string) $left.(string) $right;
        }

        return $left + $right;
    }

    private function isOperator(string ...$operators): bool
    {
        $token = $this->tokens[$this->position] ?? null;

        return $token !== null
            && $token['type'] === 'operator'
            && in_array($token['value'], $operators, true);
    }
}
