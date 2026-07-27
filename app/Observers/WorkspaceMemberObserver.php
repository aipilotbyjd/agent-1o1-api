<?php

namespace App\Observers;

use App\Authorization\WorkspaceContext;
use App\Models\Workspaces\WorkspaceMember;

class WorkspaceMemberObserver
{
    public function saved(WorkspaceMember $member): void
    {
        WorkspaceContext::forget($member->workspace_id, $member->user_id);
    }

    public function deleted(WorkspaceMember $member): void
    {
        WorkspaceContext::forget($member->workspace_id, $member->user_id);
    }

    public function restored(WorkspaceMember $member): void
    {
        WorkspaceContext::forget($member->workspace_id, $member->user_id);
    }
}
