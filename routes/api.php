<?php

use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Billing\PlanController;
use App\Http\Controllers\Api\V1\Triggers\WebhookController;
use App\Http\Controllers\Api\V1\Workflows\ShowSharedWorkflowController;
use App\Http\Controllers\Api\V1\Workspaces\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file only wires together the top-level route groups and their
| shared middleware. Each domain's actual routes live in routes/api/*.php,
| split to mirror app/Http/Controllers/Api/V1/*'s folder structure.
|
*/

Route::prefix('v1')->as('v1.')->group(function (): void {

    // Public — protected by the `signed` middleware, not auth:api.
    Route::get('auth/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:auth'])
        ->name('auth.verify-email');

    // Public — authenticated by the trigger's secret token.
    Route::post('hooks/{token}', WebhookController::class)
        ->middleware('throttle:trigger-hooks')
        ->name('hooks.trigger');

    // Public — anonymous visitors following a workflow share link.
    Route::get('shared-workflows/{token}', ShowSharedWorkflowController::class)->name('shared-workflows.show');

    require __DIR__.'/api/auth.php';

    Route::middleware(['auth:api', 'verified'])->group(function (): void {
        require __DIR__.'/api/catalog.php';

        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

        Route::get('workspaces/invitations/{token}/accept', AcceptInvitationController::class)
            ->middleware('signed')
            ->name('workspaces.invitations.accept');

        Route::prefix('workspaces')->as('workspaces.')->group(function (): void {
            require __DIR__.'/api/workspaces.php';
        });

        // Every route below is scoped to a {workspace}; workspace.context resolves the
        // workspace and the caller's role (or 404/403s) before any controller runs.
        Route::prefix('workspaces')->as('workspaces.')->middleware('workspace.context')->group(function (): void {
            require __DIR__.'/api/agents.php';
            require __DIR__.'/api/agent-skills.php';
            require __DIR__.'/api/credentials.php';
            require __DIR__.'/api/variables.php';
            require __DIR__.'/api/nodes.php';
            require __DIR__.'/api/workflows.php';
            require __DIR__.'/api/runs.php';
            require __DIR__.'/api/workflow-builder.php';
            require __DIR__.'/api/billing.php';
        });

        require __DIR__.'/api/notifications.php';
    });
});
