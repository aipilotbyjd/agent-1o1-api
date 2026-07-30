<?php

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Nodes\Node;
use App\Services\Workflows\Nodes\ConfigSchemaValidator;
use App\Services\Workflows\Nodes\Custom\CustomNode;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeCatalogSync;
use App\Services\Workflows\Nodes\NodeDefinition;
use App\Services\Workflows\Nodes\NodeRegistry;
use App\Services\Workflows\Nodes\NodeResolver;
use App\Services\Workflows\Nodes\StepNodeResolver;

it('registers a node definition for every workflow step type', function () {
    $registry = app(NodeRegistry::class);

    foreach (WorkflowStepType::cases() as $stepType) {
        expect($registry->forStepType($stepType))->not->toBeEmpty(
            "No node definition covers step type [{$stepType->value}].",
        );
    }
});

it('exposes every connector as executable', function () {
    $connectors = app(NodeRegistry::class)->connectors();

    expect($connectors)->not->toBeEmpty();

    foreach ($connectors as $type => $definition) {
        expect($definition)->toBeInstanceOf(ExecutableNode::class)
            ->and($definition->stepType())->toBe(WorkflowStepType::Tool, "[{$type}] should be a tool step.");
    }
});

it('gives every definition a unique type and a non-empty schema', function () {
    $registry = app(NodeRegistry::class);
    $types = array_keys($registry->all());

    expect($types)->toBe(array_unique($types));

    foreach ($registry->all() as $type => $definition) {
        expect($definition)->toBeInstanceOf(NodeDefinition::class)
            ->and($definition->type())->toBe($type)
            ->and($definition->configSchema()['type'] ?? null)->toBe('object', "[{$type}] schema must be an object.")
            ->and($definition->name())->not->toBe('')
            ->and($definition->description())->not->toBe('');
    }
});

it('projects the registry into the nodes table', function () {
    $result = app(NodeCatalogSync::class)->sync();

    $registry = app(NodeRegistry::class);

    expect($result['nodes'])->toBe(count($registry->all()));

    foreach ($registry->all() as $type => $definition) {
        $node = Node::query()->where('type', $type)->first();

        expect($node)->not->toBeNull("[{$type}] was not projected into the nodes table.")
            ->and($node->name)->toBe($definition->name())
            ->and($node->step_type)->toBe($definition->stepType())
            ->and($node->config_schema)->toBe($definition->configSchema())
            ->and($node->is_custom)->toBeFalse();
    }
});

it('is idempotent when synced twice', function () {
    app(NodeCatalogSync::class)->sync();
    $first = Node::query()->count();

    app(NodeCatalogSync::class)->sync();

    expect(Node::query()->count())->toBe($first);
});

it('leaves workspace custom nodes untouched when syncing', function () {
    $custom = Node::factory()->custom()->create();

    app(NodeCatalogSync::class)->sync();

    expect(Node::query()->find($custom->id))->not->toBeNull()
        ->and(Node::query()->find($custom->id)->is_custom)->toBeTrue();
});

it('resolves a step to the node that actually drives it', function () {
    $resolver = app(StepNodeResolver::class);

    expect($resolver->typeFor(['type' => 'transform', 'config' => []]))->toBe('core.transform')
        ->and($resolver->typeFor(['type' => 'tool', 'config' => ['node' => 'slack.post_message']]))->toBe('slack.post_message')
        ->and($resolver->typeFor(['type' => 'nonsense', 'config' => []]))->toBeNull();
});

it('resolves a step to a workspace-authored node, not just the code registry', function () {
    $node = Node::factory()->custom()->create();

    expect(app(StepNodeResolver::class)->typeFor(['type' => 'tool', 'config' => ['node' => $node->type]]))
        ->toBe($node->type)
        ->and(app(NodeResolver::class)->executable($node->type))->toBeInstanceOf(CustomNode::class);
});

it('does not resolve a custom node that has been deactivated', function () {
    $node = Node::factory()->custom()->create(['is_active' => false]);

    expect(app(NodeResolver::class)->has($node->type))->toBeFalse();
});

describe('config schema validation', function () {
    it('reports a missing required field', function () {
        $issues = app(ConfigSchemaValidator::class)->issues(
            ['type' => 'object', 'required' => ['agent_id'], 'properties' => []],
            [],
            'ask',
        );

        expect($issues)->toBe(['Step [ask] is missing required config field [agent_id].']);
    });

    it('reports a field of the wrong type', function () {
        $issues = app(ConfigSchemaValidator::class)->issues(
            ['type' => 'object', 'properties' => ['seconds' => ['type' => 'integer']]],
            ['seconds' => ['not', 'an', 'int']],
            'wait',
        );

        expect($issues)->toBe(['Step [wait] config field [seconds] must be of type integer, array given.']);
    });

    it('accepts a template string where a scalar is expected', function () {
        $issues = app(ConfigSchemaValidator::class)->issues(
            ['type' => 'object', 'properties' => ['seconds' => ['type' => 'integer']]],
            ['seconds' => '{{ input.wait }}'],
            'wait',
        );

        expect($issues)->toBe([]);
    });
});
