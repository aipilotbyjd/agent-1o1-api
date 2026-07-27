<?php

namespace App\Enums\Workspaces;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Viewer => Permission::viewerGrants(),
            self::Member => [...self::Viewer->permissions(), ...Permission::memberGrants()],
            self::Editor => [...self::Member->permissions(), ...Permission::editorGrants()],
            self::Admin => [...self::Editor->permissions(), ...Permission::adminGrants()],
            self::Owner => [...self::Admin->permissions(), ...Permission::ownerGrants()],
        };
    }

    public function has(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Roles that may be assigned via invite/update-role endpoints. Owner is never assignable —
     * it is derived solely from Workspace.owner_id.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Editor, self::Member, self::Viewer];
    }
}
