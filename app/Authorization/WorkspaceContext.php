<?php

namespace App\Authorization;

use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;

class WorkspaceContext
{
    private const NON_MEMBER_SENTINEL = '__none__';

    private const CACHE_TTL_MINUTES = 5;

    private const MEMO_HIDDEN_KEY = 'workspace-context.role-memo';

    public function __construct(
        public readonly Workspace $workspace,
        public readonly Role $role,
    ) {}

    public function allows(Permission $permission): bool
    {
        return $this->role->has($permission);
    }

    /**
     * Resolves the caller's role for the workspace. Memoized via Laravel's request-scoped
     * Context (not a plain static array — a static array would leak across requests under
     * Octane, and across test cases within a single Pest process).
     */
    public static function resolveRole(Workspace $workspace, User $user): ?Role
    {
        $memoKey = self::memoKey($workspace->id, $user->id);
        $memo = Context::getHidden(self::MEMO_HIDDEN_KEY, []);

        if (array_key_exists($memoKey, $memo)) {
            return $memo[$memoKey];
        }

        if ($workspace->owner_id === $user->id) {
            return self::remember($memo, $memoKey, Role::Owner);
        }

        $roleValue = Cache::remember(
            self::cacheKey($workspace->id, $user->id),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            // ->toBase() bypasses Eloquent's Role cast so we always cache a raw string.
            fn () => WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->toBase()
                ->value('role') ?? self::NON_MEMBER_SENTINEL,
        );

        $role = $roleValue === self::NON_MEMBER_SENTINEL ? null : Role::from($roleValue);

        return self::remember($memo, $memoKey, $role);
    }

    public static function cacheKey(int $workspaceId, int $userId): string
    {
        return "workspace:{$workspaceId}:member:{$userId}:role";
    }

    public static function forget(int $workspaceId, int $userId): void
    {
        Cache::forget(self::cacheKey($workspaceId, $userId));

        $memoKey = self::memoKey($workspaceId, $userId);
        $memo = Context::getHidden(self::MEMO_HIDDEN_KEY, []);
        unset($memo[$memoKey]);
        Context::addHidden(self::MEMO_HIDDEN_KEY, $memo);
    }

    /**
     * @param  array<string, Role|null>  $memo
     */
    private static function remember(array $memo, string $memoKey, ?Role $role): ?Role
    {
        $memo[$memoKey] = $role;
        Context::addHidden(self::MEMO_HIDDEN_KEY, $memo);

        return $role;
    }

    private static function memoKey(int $workspaceId, int $userId): string
    {
        return "{$workspaceId}:{$userId}";
    }
}
