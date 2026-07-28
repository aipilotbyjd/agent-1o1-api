<?php

namespace App\Http\Middleware;

use App\Authorization\WorkspaceContext;
use App\Models\Workspaces\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        abort_unless($workspace instanceof Workspace, 404);

        $role = WorkspaceContext::resolveRole($workspace, $request->user());

        abort_if($role === null, 403);

        app()->instance(WorkspaceContext::class, new WorkspaceContext($workspace, $role));

        return $next($request);
    }
}
