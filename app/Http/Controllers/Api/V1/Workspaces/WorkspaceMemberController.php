<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\InviteWorkspaceMemberRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateWorkspaceMemberRoleRequest;
use App\Http\Resources\V1\WorkspaceInvitationResource;
use App\Http\Resources\V1\WorkspaceMemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceInvitationService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly WorkspaceInvitationService $invitationService,
    ) {}

    public function index(Workspace $workspace): JsonResponse
    {
        $members = $workspace->members()->with('user')->get();

        return ApiResponse::success(WorkspaceMemberResource::collection($members));
    }

    public function invite(InviteWorkspaceMemberRequest $request, Workspace $workspace): JsonResponse
    {
        $invitation = $this->invitationService->invite(
            $workspace,
            $request->user(),
            $request->string('email')->value(),
            $request->string('role')->value(),
        );

        return ApiResponse::created(['email' => $invitation->email, 'role' => $invitation->role], 'Invitation sent successfully');
    }

    public function updateRole(UpdateWorkspaceMemberRoleRequest $request, Workspace $workspace, WorkspaceMember $member): JsonResponse
    {
        $this->ensureMemberBelongsToWorkspace($workspace, $member);

        $member = $this->workspaceService->updateMemberRole($member, $request->string('role')->value());

        return ApiResponse::success(new WorkspaceMemberResource($member->load('user')), 'Member role updated successfully');
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceMember $member): JsonResponse
    {
        $this->ensureMemberBelongsToWorkspace($workspace, $member);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $this->workspaceService->removeMember($member);

        return ApiResponse::success(null, 'Member removed successfully');
    }

    public function leave(Request $request, Workspace $workspace): JsonResponse
    {
        $this->workspaceService->leave($workspace, $request->user());

        return ApiResponse::success(null, 'You have left the workspace');
    }

    public function invitations(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $invitations = $this->invitationService->pending($workspace);

        return ApiResponse::success(WorkspaceInvitationResource::collection($invitations));
    }

    private function ensureMemberBelongsToWorkspace(Workspace $workspace, WorkspaceMember $member): void
    {
        abort_if($member->workspace_id !== $workspace->id, 404);
    }
}
