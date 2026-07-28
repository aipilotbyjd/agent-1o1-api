<?php

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('resolves the owner without a database query', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    DB::enableQueryLog();
    $role = WorkspaceContext::resolveRole($workspace, $owner);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($role)->toBe(Role::Owner);
    expect($queryCount)->toBe(0);
});

it('resolves a member role and caches it across calls', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $first = WorkspaceContext::resolveRole($workspace, $user);

    DB::enableQueryLog();
    $second = WorkspaceContext::resolveRole($workspace, $user);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($first)->toBe(Role::Admin);
    expect($second)->toBe(Role::Admin);
    expect($queryCount)->toBe(0);
    expect(Cache::has(WorkspaceContext::cacheKey($workspace->id, $user->id)))->toBeTrue();
});

it('resolves a non-member to null rather than granting an implicit role', function () {
    $outsider = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $role = WorkspaceContext::resolveRole($workspace, $outsider);

    expect($role)->toBeNull();
});

it('clears the cached role on forget', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    WorkspaceContext::resolveRole($workspace, $user);
    expect(Cache::has(WorkspaceContext::cacheKey($workspace->id, $user->id)))->toBeTrue();

    WorkspaceContext::forget($workspace->id, $user->id);

    expect(Cache::has(WorkspaceContext::cacheKey($workspace->id, $user->id)))->toBeFalse();
});
