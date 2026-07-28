<?php

namespace App\Services\Workspaces;

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkspaceService
{
    /**
     * @param  array{name: string, slug?: string|null}  $data
     */
    public function create(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
                'owner_id' => $owner->id,
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => Role::Owner,
                'joined_at' => now(),
            ]);

            return $workspace;
        });
    }

    /**
     * @param  array{name?: string, slug?: string}  $data
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        return $workspace;
    }

    public function delete(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace) {
            $workspace->members()->delete();
            $workspace->invitations()->delete();
            $workspace->delete();
        });
    }

    public function updateAvatar(Workspace $workspace, UploadedFile $avatar): Workspace
    {
        if ($workspace->avatar) {
            Storage::disk('public')->delete($workspace->avatar);
        }

        $workspace->update([
            'avatar' => $avatar->store('workspaces/avatars', 'public'),
        ]);

        return $workspace;
    }

    public function updateMemberRole(WorkspaceMember $member, Role $role): WorkspaceMember
    {
        if ($member->role === Role::Owner) {
            throw new HttpException(422, 'The workspace owner\'s role cannot be changed.');
        }

        $member->update(['role' => $role]);

        return $member;
    }

    public function removeMember(WorkspaceMember $member): void
    {
        if ($member->role === Role::Owner) {
            throw new HttpException(422, 'The workspace owner cannot be removed.');
        }

        $member->delete();
    }

    public function leave(Workspace $workspace, User $user): void
    {
        $member = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($member->role === Role::Owner) {
            throw new AuthorizationException('The workspace owner must transfer ownership before leaving.');
        }

        $member->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
