<?php

namespace App\Notifications\Billing;

use App\Enums\Notifications\NotificationEvent;
use App\Models\Workspaces\Workspace;
use App\Notifications\Workspace\WorkspaceEventNotification;

class PaymentFailedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace)
    {
        parent::__construct(
            workspace: $workspace,
            event: NotificationEvent::PaymentFailed,
            title: "Payment failed for {$workspace->name}",
            body: 'We were unable to charge your payment method. Please update your billing details to avoid service interruption.',
        );
    }
}
