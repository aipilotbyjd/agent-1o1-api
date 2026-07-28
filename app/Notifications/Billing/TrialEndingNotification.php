<?php

namespace App\Notifications\Billing;

use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

class TrialEndingNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, int $daysRemaining)
    {
        parent::__construct(
            workspace: $workspace,
            eventKey: 'billing.trial_ending',
            title: "Your trial for {$workspace->name} ends in {$daysRemaining} day(s)",
            body: 'Add a payment method to keep your subscription active once the trial ends.',
        );
    }
}
