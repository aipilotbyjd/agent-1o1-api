<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationDispatcher
{
    /**
     * @param  iterable<int, User>  $recipients
     */
    public function dispatch(iterable $recipients, Notification $notification): void
    {
        NotificationFacade::send($recipients, $notification);
    }

    /**
     * Workspace owners and admins, optionally excluding one user (typically the actor).
     *
     * @return Collection<int, User>
     */
    public function ownersAndAdmins(Workspace $workspace, ?User $except = null): Collection
    {
        return $workspace->users()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get()
            ->when($except, fn (Collection $users) => $users->reject(fn (User $user) => $user->id === $except->id))
            ->values();
    }
}
