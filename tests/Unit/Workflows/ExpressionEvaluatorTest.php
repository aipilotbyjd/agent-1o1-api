<?php

use App\Exceptions\Workflows\InvalidExpressionException;
use App\Services\Workflows\ExpressionEvaluator;

beforeEach(function (): void {
    $this->evaluator = new ExpressionEvaluator;
});

it('evaluates arithmetic with correct precedence', function () {
    expect($this->evaluator->evaluate('2 + 3 * 4', []))->toBe(14.0)
        ->and($this->evaluator->evaluate('(2 + 3) * 4', []))->toBe(20.0);
});

it('reads values from the context by dot path', function () {
    $context = ['input' => ['user' => ['age' => 36]]];

    expect($this->evaluator->evaluate('input.user.age', $context))->toBe(36)
        ->and($this->evaluator->evaluate('input.user.age > 18', $context))->toBeTrue();
});

it('evaluates comparison and logical operators', function () {
    $context = ['input' => ['count' => 5, 'name' => 'ada']];

    expect($this->evaluator->evaluate("input.count > 3 && input.name == 'ada'", $context))->toBeTrue()
        ->and($this->evaluator->evaluate('input.count > 10 || input.count < 1', $context))->toBeFalse()
        ->and($this->evaluator->evaluate('!(input.count == 5)', $context))->toBeFalse();
});

it('concatenates with + when either side is a non-numeric string', function () {
    expect($this->evaluator->evaluate("'a' + 'b'", []))->toBe('ab')
        ->and($this->evaluator->evaluate("'n=' + 5", []))->toBe('n=5');
});

it('supports the allowlisted functions', function () {
    $context = ['input' => ['tags' => ['a', 'b', 'c'], 'name' => ' Ada ']];

    expect($this->evaluator->evaluate('length(input.tags)', $context))->toBe(3)
        ->and($this->evaluator->evaluate('trim(input.name)', $context))->toBe('Ada')
        ->and($this->evaluator->evaluate('upper(trim(input.name))', $context))->toBe('ADA')
        ->and($this->evaluator->evaluate('round(2.567, 2)', []))->toBe(2.57)
        ->and($this->evaluator->evaluate("coalesce(input.missing, 'fallback')", $context))->toBe('fallback')
        ->and($this->evaluator->evaluate("contains(input.tags, 'b')", $context))->toBeTrue();
});

it('resolves an unknown path to null rather than failing', function () {
    expect($this->evaluator->evaluate('input.nope', ['input' => []]))->toBeNull();
});

it('refuses to call a function that is not allowlisted', function () {
    expect(fn () => $this->evaluator->evaluate("system('ls')", []))
        ->toThrow(InvalidExpressionException::class);
})->with([
    'system',
    'exec',
    'file_get_contents',
    'eval',
]);

it('rejects php superglobals and object access syntax', function (string $expression) {
    expect(fn () => $this->evaluator->evaluate($expression, []))
        ->toThrow(InvalidExpressionException::class);
})->with([
    '$_SERVER',
    'a->b',
    'a::b',
    '`ls`',
    '1; DROP TABLE users',
]);

it('rejects division by zero instead of emitting a warning', function () {
    expect(fn () => $this->evaluator->evaluate('1 / 0', []))
        ->toThrow(InvalidExpressionException::class, 'Division by zero in expression.');
});

it('rejects an unterminated string', function () {
    expect(fn () => $this->evaluator->evaluate("'unterminated", []))
        ->toThrow(InvalidExpressionException::class);
});

it('rejects a missing closing parenthesis', function () {
    expect(fn () => $this->evaluator->evaluate('(1 + 2', []))
        ->toThrow(InvalidExpressionException::class);
});
