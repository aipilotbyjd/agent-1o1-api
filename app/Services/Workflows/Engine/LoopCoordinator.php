<?php

namespace App\Services\Workflows\Engine;

use App\Enums\Runs\RunStatus;
use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\TemplateResolver;
use Illuminate\Support\Collection;

/**
 * Runs a `foreach` loop step as a fan-out of child runs.
 *
 * Each iteration is a real child run of the configured workflow, so items execute on the
 * queue in parallel, fail in isolation, and are inspectable individually — rather than
 * being flattened into one synchronous pass inside a handler. That makes the loop a
 * pause-and-join like a sub-workflow rather than something a StepHandler could express.
 */
class LoopCoordinator
{
    public function __construct(
        private readonly RunStarter $starter,
        private readonly GraphAdvancer $advancer,
        private readonly StepFailureHandler $failures,
        private readonly StepContextBuilder $contexts,
        private readonly TemplateResolver $templates,
        private readonly RunLogger $logger,
    ) {}

    /**
     * Fan a loop step out into one child run per item.
     *
     * @param  array<string, mixed>  $step
     */
    public function start(Run $run, RunStep $runStep, array $step): void
    {
        $config = $step['config'] ?? [];
        $context = $this->contexts->build($run, $step);
        $items = data_get($context, (string) ($config['items'] ?? ''));

        if (! is_array($items)) {
            $this->failures->handle($run, $runStep, $step, "Step [{$step['key']}] items path did not resolve to a list.");

            return;
        }

        $workflow = Workflow::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['workflow_id'] ?? null);

        if ($workflow === null) {
            $this->failures->handle($run, $runStep, $step, "Step [{$step['key']}] references a missing workflow.");

            return;
        }

        $items = array_values($items);
        $runStep->markRunning();

        // The resolved items are persisted on the step so later iterations can be started
        // from a queue worker that no longer has the context that produced them.
        $runStep->update(['input' => [...$config, '_loop_items' => $items]]);

        if ($items === []) {
            $empty = ['results' => [], 'count' => 0, 'failed' => 0];

            $runStep->markCompleted($empty);
            $this->advancer->advance($run, $step, $empty);

            return;
        }

        $concurrency = max(1, (int) ($config['max_concurrent'] ?? count($items)));

        // The whole opening batch is started before joining. On a synchronous queue a
        // child finishes inside its own dispatch, and joining there would see a partial
        // batch and release iterations this loop is already about to start.
        foreach (range(0, min($concurrency, count($items)) - 1) as $index) {
            $this->startItem($run, $runStep, $workflow, $items[$index], $index, $context, join: false);
        }

        $this->join($run, $runStep);
    }

    /**
     * Join a loop step: start any queued iterations, then finish once all have settled.
     */
    public function join(Run $run, RunStep $runStep): void
    {
        $runStep->refresh();

        if ($runStep->status->isTerminal()) {
            return;
        }

        $config = $runStep->input ?? [];
        $items = $config['_loop_items'] ?? [];
        $policy = $config['on_item_error'] ?? 'fail_fast';

        $children = Run::query()->where('parent_step_id', $runStep->id)->orderBy('loop_index')->get();
        $failed = $children->filter(fn (Run $child): bool => $child->status === RunStatus::Failed);

        if ($failed->isNotEmpty() && $policy === 'fail_fast') {
            $this->finish($run, $runStep, $children, $items, $policy);

            return;
        }

        if ($children->contains(fn (Run $child): bool => ! $child->status->isTerminal())) {
            return;
        }

        // Every started iteration has settled; release the next queued one if any remain.
        if ($children->count() < count($items)) {
            $workflow = Workflow::query()->find($config['workflow_id'] ?? null);
            $index = $children->count();

            if ($workflow !== null) {
                $this->startItem($run, $runStep, $workflow, $items[$index], $index, $this->contexts->build($run));

                return;
            }
        }

        $this->finish($run, $runStep, $children, $items, $policy);
    }

    /**
     * Start one iteration's child run.
     *
     * @param  array<string, mixed>  $context
     */
    private function startItem(Run $run, RunStep $runStep, Workflow $workflow, mixed $item, int $index, array $context, bool $join = true): void
    {
        $config = $runStep->input ?? [];

        $input = $this->templates->resolveArray(
            $config['input'] ?? [],
            [...$context, 'item' => $item, 'index' => $index],
        );

        $child = $this->starter->start(
            $workflow,
            $run->triggeredBy,
            $input === [] ? ['item' => $item, 'index' => $index] : $input,
            'loop',
            $run->environment,
        );

        $child->update([
            'parent_run_id' => $run->id,
            'parent_step_id' => $runStep->id,
            'loop_index' => $index,
        ]);

        // On the sync queue the child has already finished by now, and the in-memory
        // instance is stale — reload before deciding whether the join can proceed.
        $child->refresh();

        if ($join && $child->status->isTerminal()) {
            $this->join($run, $runStep);
        }
    }

    /**
     * @param  Collection<int, Run>  $children
     * @param  array<int, mixed>  $items
     */
    private function finish(Run $run, RunStep $runStep, Collection $children, array $items, string $policy): void
    {
        $results = $children->map(fn (Run $child): array => [
            'index' => $child->loop_index,
            'run_id' => $child->id,
            'status' => $child->status->value,
            'output' => $child->output,
            'error' => $child->error,
        ])->values()->all();

        $failed = $children->filter(fn (Run $child): bool => $child->status !== RunStatus::Completed);

        $output = [
            'results' => $results,
            'count' => count($results),
            'failed' => $failed->count(),
            'errors' => $failed->map(fn (Run $child): array => [
                'index' => $child->loop_index,
                'error' => $child->error,
            ])->values()->all(),
        ];

        $step = $this->advancer->graphFor($run)->step($runStep->key) ?? ['key' => $runStep->key, 'config' => []];

        // fail_fast stops the run on the first bad item; collect_errors and continue both
        // carry on, differing only in whether the failures stay visible in the output.
        if ($failed->isNotEmpty() && $policy === 'fail_fast') {
            $this->failures->handle(
                $run,
                $runStep,
                $step,
                "Loop step [{$runStep->key}] had {$failed->count()} failed iteration(s).",
            );

            return;
        }

        if ($policy === 'continue') {
            $output['results'] = array_values(array_filter(
                $results,
                fn (array $result): bool => $result['status'] === RunStatus::Completed->value,
            ));
        }

        $run->markRunning();
        $runStep->markCompleted($output);
        $this->logger->info($run, "Step [{$runStep->key}] completed ".count($items).' iteration(s).', $runStep->key);

        $this->advancer->advance($run, $step, $output);
    }
}
