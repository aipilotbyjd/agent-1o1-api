<?php

use App\Enums\Workspaces\Permission;
use App\Enums\Workspaces\Role;

it('composes each tier as a strict superset of the tier below', function () {
    $tiers = [Role::Viewer, Role::Member, Role::Editor, Role::Admin, Role::Owner];

    foreach (array_slice($tiers, 1) as $index => $tier) {
        $below = array_map(fn ($permission) => $permission->value, $tiers[$index]->permissions());
        $above = array_map(fn ($permission) => $permission->value, $tier->permissions());

        expect(array_intersect($below, $above))->toHaveCount(count($below));
        expect(count($above))->toBeGreaterThan(count($below));
    }
});

it('excludes owner from assignable roles', function () {
    expect(Role::assignable())->not->toContain(Role::Owner);
    expect(Role::assignable())->toContain(Role::Admin, Role::Editor, Role::Member, Role::Viewer);
});

it('grants owner every permission admin has, plus workspace deletion', function () {
    expect(Role::Owner->permissions())->toContain(...Role::Admin->permissions());
    expect(Role::Owner->has(Permission::WorkspaceDelete))->toBeTrue();
    expect(Role::Admin->has(Permission::WorkspaceDelete))->toBeFalse();
});
