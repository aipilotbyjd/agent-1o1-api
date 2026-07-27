<?php

namespace App\Services\Workspaces;

use App\Enums\Workspaces\Role;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkspaceInvitationService
{
    /**
     * @return Collection<int, WorkspaceInvitation>
     */
    public function pending(Workspace $workspace): Collection
    {
        return WorkspaceInvitation::where('workspace_id', $workspace->id)
            ->pending()
            ->latest()
            ->get();
    }

    public function invite(Workspace $workspace, User $inviter, string $email, Role $role): WorkspaceInvitation
    {
        $alreadyMember = WorkspaceMember::where('workspace_id', $workspace->id)
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->exists();

        if ($alreadyMember) {
            throw new HttpException(422, 'This user is already a member of the workspace.');
        }

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(40),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->queue(
            new WorkspaceInvitationMail($invitation, $this->acceptUrl($invitation)),
        );

        return $invitation;
    }

    public function accept(WorkspaceInvitation $invitation, User $user): WorkspaceMember
    {
        if ($invitation->isAccepted()) {
            throw new HttpException(422, 'This invitation has already been accepted.');
        }

        if ($invitation->isExpired()) {
            throw new HttpException(422, 'This invitation has expired.');
        }

        if ($invitation->email !== $user->email) {
            throw new HttpException(422, 'This invitation was not addressed to your account.');
        }

        return DB::transaction(function () use ($invitation, $user) {
            $member = WorkspaceMember::withTrashed()
                ->where('workspace_id', $invitation->workspace_id)
                ->where('user_id', $user->id)
                ->first();

            if ($member) {
                $member->restore();
                $member->update(['role' => $invitation->role, 'invited_by' => $invitation->invited_by, 'joined_at' => now()]);
            } else {
                $member = WorkspaceMember::create([
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                    'invited_by' => $invitation->invited_by,
                    'joined_at' => now(),
                ]);
            }

            $invitation->update(['accepted_at' => now()]);

            return $member;
        });
    }

    private function acceptUrl(WorkspaceInvitation $invitation): string
    {
        return URL::temporarySignedRoute(
            'v1.workspaces.invitations.accept',
            $invitation->expires_at,
            ['token' => $invitation->token],
        );
    }
}
