<?php

namespace App\Notifications\Workspace;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

class MemberInvitedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, WorkspaceInvitation $invitation, User $inviter)
    {
        parent::__construct(
            workspace: $workspace,
            eventKey: 'workspace.member_invited',
            title: "{$inviter->name} invited {$invitation->email} to {$workspace->name}",
            data: [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'invited_by' => $inviter->id,
            ],
        );
    }
}
