<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('workspace.{workspaceId}', function (User $user, int $workspaceId) {
    return $user->workspaces()->where('workspaces.id', $workspaceId)->exists();
});
