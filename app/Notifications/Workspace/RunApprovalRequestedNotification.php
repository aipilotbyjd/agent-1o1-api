<?php

namespace App\Notifications\Workspace;

use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Models\Workspaces\Workspace;

class RunApprovalRequestedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, Run $run, RunStep $step, string $message)
    {
        parent::__construct(
            workspace: $workspace,
            eventKey: 'run.approval_requested',
            title: "Approval needed in {$workspace->name}",
            body: $message,
            data: [
                'run_id' => $run->id,
                'step_id' => $step->id,
                'step_key' => $step->key,
            ],
        );
    }
}
