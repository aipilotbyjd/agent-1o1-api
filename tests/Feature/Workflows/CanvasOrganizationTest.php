<?php

use App\Models\User;
use App\Models\Workflows\Folder;
use App\Models\Workflows\StickyNote;
use App\Models\Workflows\Tag;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function canvasWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('creates and lists folders with nesting', function () {
    [$user, $workspace] = canvasWorkspace();

    $parent = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", ['name' => 'Marketing'])
        ->assertCreated()
        ->json('data');

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", ['name' => 'Emails', 'parent_id' => $parent['id']])
        ->assertCreated();

    $this->withToken(authHeader($user))
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.children.0.name', 'Emails');
});

it('moves workflows into a folder and releases them on folder delete', function () {
    [$user, $workspace] = canvasWorkspace();
    $folder = Folder::factory()->create(['workspace_id' => $workspace->id]);
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders/move-workflows", [
            'workflow_ids' => [$workflow->id],
            'folder_id' => $folder->id,
        ])->assertOk();

    expect($workflow->fresh()->folder_id)->toBe($folder->id);

    $this->withToken(authHeader($user))
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/folders/{$folder->id}")
        ->assertOk();

    expect($workflow->fresh()->folder_id)->toBeNull();
});

it('rejects moving workflows to a folder from another workspace', function () {
    [$user, $workspace] = canvasWorkspace();
    $foreignFolder = Folder::factory()->create();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders/move-workflows", [
            'workflow_ids' => [$workflow->id],
            'folder_id' => $foreignFolder->id,
        ])->assertUnprocessable();
});

it('forbids a viewer from creating folders', function () {
    [$user, $workspace] = canvasWorkspace('viewer');

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", ['name' => 'Nope'])
        ->assertForbidden();
});

it('creates tags and prevents duplicates per workspace', function () {
    [$user, $workspace] = canvasWorkspace();

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/tags", ['name' => 'prod', 'color' => '#ff0000'])
        ->assertCreated();

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/tags", ['name' => 'prod'])
        ->assertUnprocessable();
});

it('syncs tags on a workflow', function () {
    [$user, $workspace] = canvasWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $tags = Tag::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/tags", [
            'tag_ids' => $tags->pluck('id')->all(),
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($workflow->tags()->count())->toBe(2);
});

it('rejects syncing a tag from another workspace', function () {
    [$user, $workspace] = canvasWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $foreignTag = Tag::factory()->create();

    $this->withToken(authHeader($user))
        ->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/tags", [
            'tag_ids' => [$foreignTag->id],
        ])->assertUnprocessable();
});

it('manages sticky notes on a workflow canvas', function () {
    [$user, $workspace] = canvasWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $note = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/sticky-notes", [
            'content' => 'Remember to add retries here',
            'position_x' => 120.5,
            'position_y' => 88,
        ])
        ->assertCreated()
        ->json('data');

    $this->withToken(authHeader($user))
        ->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/sticky-notes/{$note['id']}", [
            'content' => 'Updated note',
        ])
        ->assertOk()
        ->assertJsonPath('data.content', 'Updated note');

    $this->withToken(authHeader($user))
        ->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/sticky-notes")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken(authHeader($user))
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/sticky-notes/{$note['id']}")
        ->assertOk();

    expect(StickyNote::query()->count())->toBe(0);
});

it('404s a sticky note accessed through the wrong workflow', function () {
    [$user, $workspace] = canvasWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $otherWorkflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $note = StickyNote::factory()->create(['workflow_id' => $workflow->id, 'workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$otherWorkflow->id}/sticky-notes/{$note->id}", [
            'content' => 'hijack',
        ])->assertNotFound();
});
