<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WorkspaceMemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcceptInvitationController extends Controller
{
    public function __construct(private readonly WorkspaceInvitationService $invitationService) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::where('token', $token)->firstOrFail();

        $member = $this->invitationService->accept($invitation, $request->user());

        return ApiResponse::success(new WorkspaceMemberResource($member->load('user')), 'Invitation accepted successfully');
    }
}
