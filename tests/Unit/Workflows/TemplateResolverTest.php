<?php

use App\Services\Workflows\TemplateResolver;

beforeEach(function (): void {
    $this->resolver = new TemplateResolver;
});

it('interpolates a scalar into a surrounding string', function () {
    expect($this->resolver->resolve('Hello {{ input.name }}!', ['input' => ['name' => 'Ada']]))
        ->toBe('Hello Ada!');
});

it('preserves the original type when the template is a single whole-string placeholder', function () {
    $context = ['input' => ['count' => 5, 'active' => true, 'ratio' => 1.5, 'tags' => ['a', 'b']]];

    expect($this->resolver->resolveValue('{{ input.count }}', $context))->toBe(5)
        ->and($this->resolver->resolveValue('{{ input.active }}', $context))->toBeTrue()
        ->and($this->resolver->resolveValue('{{ input.ratio }}', $context))->toBe(1.5)
        ->and($this->resolver->resolveValue('{{ input.tags }}', $context))->toBe(['a', 'b']);
});

it('stringifies values when interpolated alongside other text', function () {
    expect($this->resolver->resolveValue('n={{ input.count }}', ['input' => ['count' => 5]]))
        ->toBe('n=5');
});

it('resolves templates nested inside arrays at any depth', function () {
    $templates = [
        'user' => [
            'name' => '{{ input.name }}',
            'meta' => [
                'age' => '{{ input.age }}',
                'tags' => ['{{ input.tag }}', 'static'],
            ],
        ],
    ];

    $resolved = $this->resolver->resolveArray($templates, [
        'input' => ['name' => 'Ada', 'age' => 36, 'tag' => 'admin'],
    ]);

    expect($resolved)->toBe([
        'user' => [
            'name' => 'Ada',
            'meta' => [
                'age' => 36,
                'tags' => ['admin', 'static'],
            ],
        ],
    ]);
});

it('resolves a missing path to null rather than the literal template', function () {
    expect($this->resolver->resolveValue('{{ input.nope }}', ['input' => []]))->toBeNull()
        ->and($this->resolver->resolve('x={{ input.nope }}', ['input' => []]))->toBe('x=');
});

it('applies the default filter when a path is missing', function () {
    expect($this->resolver->resolveValue('{{ input.nope | default:fallback }}', ['input' => []]))
        ->toBe('fallback')
        ->and($this->resolver->resolveValue('{{ input.name | default:fallback }}', ['input' => ['name' => 'Ada']]))
        ->toBe('Ada');
});

it('applies the int filter to coerce a numeric string', function () {
    expect($this->resolver->resolveValue('{{ input.count | int }}', ['input' => ['count' => '42']]))
        ->toBe(42);
});

it('applies the json filter to encode a structure', function () {
    expect($this->resolver->resolveValue('{{ input.tags | json }}', ['input' => ['tags' => ['a', 'b']]]))
        ->toBe('["a","b"]');
});

it('applies the upper and lower filters', function () {
    expect($this->resolver->resolveValue('{{ input.name | upper }}', ['input' => ['name' => 'ada']]))->toBe('ADA')
        ->and($this->resolver->resolveValue('{{ input.name | lower }}', ['input' => ['name' => 'ADA']]))->toBe('ada');
});

it('leaves non-string values in an array untouched', function () {
    $resolved = $this->resolver->resolveArray(['count' => 5, 'flag' => true, 'nothing' => null], []);

    expect($resolved)->toBe(['count' => 5, 'flag' => true, 'nothing' => null]);
});
