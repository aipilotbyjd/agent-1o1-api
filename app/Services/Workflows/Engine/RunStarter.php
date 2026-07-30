<?php

namespace App\Services\Workflows\Engine;

use App\Jobs\Workflows\ExecuteWorkflowStep;
use App\Models\Runs\Run;
use App\Models\Runs\RunReplayPack;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\WorkspaceEnvironment;
use App\Services\Workflows\WorkflowGraph;

/**
 * Creates runs and kicks off their entry steps.
 *
 * Separate from the runner so the loop and sub-workflow coordinators can spawn child
 * runs without depending on the runner that owns them.
 */
class RunStarter
{
    public function __construct(private readonly RunLogger $logger) {}

    /**
     * Start a run against the workflow's current published version.
     *
     * @param  array<string, mixed>  $input
     */
    public function start(
        Workflow $workflow,
        ?User $user,
        array $input,
        string $triggerType = 'manual',
        ?WorkspaceEnvironment $environment = null,
        ?WorkflowVersion $version = null,
    ): Run {
        $version ??= $workflow->currentVersion;

        $run = Run::create([
            'workspace_id' => $workflow->workspace_id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_version_id' => $version?->id,
            'environment_id' => $environment?->id,
            'trigger_type' => $triggerType,
            'input' => $input,
            'triggered_by' => $user?->id,
        ]);

        $run->markRunning();
        $this->logger->info($run, 'Run started.');

        if ($version === null) {
            $this->logger->error($run, 'Workflow has no published version.');
            $run->markFailed('Workflow has no published version.');

            return $run;
        }

        $entrySteps = WorkflowGraph::fromArray($version->graph)->entrySteps();

        if ($entrySteps === []) {
            $this->logger->error($run, 'Workflow has no steps.');
            $run->markFailed('Workflow has no steps.');

            return $run;
        }

        foreach ($entrySteps as $step) {
            ExecuteWorkflowStep::dispatch($run->id, $step['key']);
        }

        return $run;
    }

    /**
     * Start a run against a replay pack's captured graph rather than the workflow's current
     * published version — materialized as a new WorkflowVersion (tagged in its notes) so the
     * run's workflow_version_id stays a real, queryable version, same as any other run.
     */
    public function replay(RunReplayPack $pack, ?User $user): Run
    {
        $workflow = $pack->workflow;

        $version = $workflow->versions()->create([
            'version' => ((int) $workflow->versions()->max('version')) + 1,
            'graph' => $pack->version_snapshot,
            'notes' => "Replay of pack #{$pack->id}".($pack->label ? " ({$pack->label})" : ''),
            'published_by' => $user?->id,
        ]);

        return $this->start($workflow, $user, $pack->trigger_data ?? [], 'replay', version: $version);
    }
}
