<?php

namespace App\Notifications\Workspace;

use App\Models\User;
use App\Models\Workspaces\Workspace;

class MemberRemovedNotification extends WorkspaceEventNotification
{
    public function __construct(Workspace $workspace, User $removedUser)
    {
        parent::__construct(
            workspace: $workspace,
            eventKey: 'workspace.member_removed',
            title: "{$removedUser->name} was removed from {$workspace->name}",
            data: [
                'user_id' => $removedUser->id,
            ],
        );
    }
}
