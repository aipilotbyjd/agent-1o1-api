<?php

namespace App\Notifications\Workspace;

use App\Models\Workspace;
use App\Models\WorkspaceMember;

class MemberRoleChangedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, WorkspaceMember $member, string $previousRole)
    {
        parent::__construct(
            workspace: $workspace,
            eventKey: 'workspace.member_role_changed',
            title: "{$member->user->name}'s role changed from {$previousRole} to {$member->role} in {$workspace->name}",
            data: [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'previous_role' => $previousRole,
                'role' => $member->role,
            ],
        );
    }
}
