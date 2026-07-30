<?php

namespace App\Services\Workflows;

use App\Services\Workflows\Nodes\NodeDefinition;
use App\Services\Workflows\Nodes\StepNodeResolver;

/**
 * Simulates a graph without calling anything external.
 *
 * Each step's config is template-resolved against a context built from sample outputs
 * derived from the preceding nodes' output schemas. That catches the authoring mistakes
 * that otherwise only surface at run time — a template pointing at a step that runs
 * later, a misspelled path, a field the node never emits — without side effects.
 */
class DryRunner
{
    public function __construct(
        private readonly GraphValidator $validator,
        private readonly StepNodeResolver $resolver,
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * @param  array{steps?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(array $graph, array $input = []): array
    {
        $issues = $this->validator->issues($graph);

        // An invalid graph has no meaningful execution order, so simulating it would
        // only produce noise on top of problems the author must fix first.
        if ($issues !== []) {
            return ['ok' => false, 'issues' => $issues, 'steps' => []];
        }

        $context = ['input' => $input, 'steps' => [], 'variables' => []];
        $warnings = [];
        $trace = [];

        foreach (WorkflowGraph::fromArray($graph)->topologicalOrder() as $step) {
            $definition = $this->resolver->definitionFor($step);
            $config = $step['config'] ?? [];

            $unresolved = $this->unresolvedPaths($config, $context);
            $warnings = [...$warnings, ...array_map(
                fn (string $path): string => "Step [{$step['key']}] references [{$path}], which nothing provides at that point.",
                $unresolved,
            )];

            $output = $this->sampleOutput($definition);
            $context['steps'][$step['key']] = $output;

            $trace[] = [
                'key' => $step['key'],
                'node' => $definition?->type(),
                'resolved_config' => $this->templates->resolveArray($config, $context),
                'sample_output' => $output,
            ];
        }

        return [
            'ok' => $warnings === [],
            'issues' => [],
            'warnings' => $warnings,
            'steps' => $trace,
        ];
    }

    /**
     * A placeholder output shaped like the node's declared output schema.
     *
     * @return array<string, mixed>
     */
    private function sampleOutput(?NodeDefinition $definition): array
    {
        $schema = $definition?->outputSchema() ?? [];
        $sample = [];

        foreach ($schema['properties'] ?? [] as $property => $rules) {
            $sample[$property] = match ($rules['type'] ?? 'string') {
                'integer' => 0,
                'number' => 0.0,
                'boolean' => true,
                'array' => [],
                'object' => [],
                default => 'sample',
            };
        }

        // Condition steps drive edge routing, so their result is modelled explicitly
        // rather than left as the generic string sample.
        if ($definition?->type() === 'core.condition') {
            $sample['result'] = 'true';
        }

        return $sample;
    }

    /**
     * Template paths in the config that the context cannot supply.
     *
     * @param  array<array-key, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function unresolvedPaths(array $config, array $context): array
    {
        $unresolved = array_filter(
            TemplatePaths::referencedIn($config),
            function (string $path) use ($context): bool {
                // `variables.*` and loop-local `item`/`index` only exist at run time.
                if (str_starts_with($path, 'variables.') || str_starts_with($path, 'item') || $path === 'index') {
                    return false;
                }

                return data_get($context, $path) === null;
            },
        );

        return array_values($unresolved);
    }
}
