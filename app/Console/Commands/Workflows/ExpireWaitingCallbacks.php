<?php

namespace App\Console\Commands\Workflows;

use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\RunStep;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workflows:expire-waiting-callbacks')]
#[Description('Settle wait steps whose callback deadline has passed')]
class ExpireWaitingCallbacks extends Command
{
    public function handle(WorkflowRunner $runner): int
    {
        $expired = RunStep::query()
            ->where('status', RunStepStatus::AwaitingCallback->value)
            ->whereNotNull('callback_expires_at')
            ->where('callback_expires_at', '<=', now())
            ->with('run')
            ->get();

        foreach ($expired as $step) {
            $runner->expireWait($step);
        }

        $this->info("Expired {$expired->count()} waiting step(s).");

        return self::SUCCESS;
    }
}
