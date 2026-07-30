<?php

namespace App\Services\Workflows\Engine;

use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Models\Variable;
use App\Services\Workflows\TemplatePaths;

/**
 * Builds the template context a step is resolved against: the trigger input, every
 * completed step's output, and the workspace variables that step is entitled to see.
 */
class StepContextBuilder
{
    /**
     * @param  array<string, mixed>|null  $step  Scopes variables to those the step names.
     * @return array<string, mixed>
     */
    public function build(Run $run, ?array $step = null): array
    {
        $steps = $run->steps()
            ->where('status', RunStepStatus::Completed->value)
            ->get()
            ->mapWithKeys(fn (RunStep $runStep): array => [$runStep->key => $runStep->output]);

        $variables = Variable::query()
            ->where('workspace_id', $run->workspace_id)
            ->get()
            ->mapWithKeys(fn (Variable $variable): array => [$variable->key => $variable->value]);

        // The run's environment (if any) overrides matching workspace variable keys —
        // e.g. a "staging" release sees a different API base URL than "production".
        if ($run->environment_id !== null) {
            $variables = $variables->merge($run->environment?->variables ?? []);
        }

        // Only the variables a step actually names are handed to it. Every step used to
        // receive every workspace secret, so one careless `{{ variables }}` mapping — or
        // one connector that echoes its input — exposed credentials the step never used.
        if ($step !== null) {
            $variables = $variables->only(TemplatePaths::variableKeysIn($step['config'] ?? []));
        }

        return ['input' => $run->input ?? [], 'steps' => $steps->all(), 'variables' => $variables->all()];
    }
}
