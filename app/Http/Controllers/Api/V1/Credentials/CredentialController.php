<?php

namespace App\Http\Controllers\Api\V1\Credentials;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Credentials\StoreCredentialRequest;
use App\Http\Requests\Api\V1\Credentials\UpdateCredentialRequest;
use App\Http\Resources\V1\Credentials\CredentialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Credentials\Credential;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::CredentialView);

        return ApiResponse::success(
            CredentialResource::collection($workspace->credentials()->latest()->get()),
        );
    }

    public function store(StoreCredentialRequest $request, Workspace $workspace): JsonResponse
    {
        $credential = $workspace->credentials()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new CredentialResource($credential), 'Credential created.');
    }

    public function show(Request $request, Workspace $workspace, Credential $credential): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $credential);

        $this->requirePermission(Permission::CredentialView);

        return ApiResponse::success(new CredentialResource($credential));
    }

    public function update(UpdateCredentialRequest $request, Workspace $workspace, Credential $credential): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $credential);

        $credential->update($request->validated());

        return ApiResponse::success(new CredentialResource($credential), 'Credential updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Credential $credential): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $credential);

        $this->requirePermission(Permission::CredentialManage);

        $credential->delete();

        return ApiResponse::success(null, 'Credential deleted.');
    }
}
