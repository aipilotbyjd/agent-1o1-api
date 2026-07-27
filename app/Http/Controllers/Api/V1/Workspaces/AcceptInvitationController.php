<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Workspaces\WorkspaceMemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Notifications\Workspace\MemberJoinedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workspaces\WorkspaceInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcceptInvitationController extends Controller
{
    public function __construct(
        private readonly WorkspaceInvitationService $invitationService,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::where('token', $token)->firstOrFail();

        $member = $this->invitationService->accept($invitation, $request->user());
        $member->load('user');

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($invitation->workspace, except: $member->user),
            new MemberJoinedNotification($invitation->workspace, $member),
        );

        return ApiResponse::success(new WorkspaceMemberResource($member), 'Invitation accepted successfully');
    }
}
