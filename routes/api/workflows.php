<?php

use App\Http\Controllers\Api\V1\Triggers\TriggerEventController;
use App\Http\Controllers\Api\V1\Triggers\WorkflowTriggerController;
use App\Http\Controllers\Api\V1\Workflows\CloneSharedWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\FolderController;
use App\Http\Controllers\Api\V1\Workflows\PublishWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\RunReplayPackController;
use App\Http\Controllers\Api\V1\Workflows\SaveWorkflowGraphController;
use App\Http\Controllers\Api\V1\Workflows\StickyNoteController;
use App\Http\Controllers\Api\V1\Workflows\SyncWorkflowTagsController;
use App\Http\Controllers\Api\V1\Workflows\TagController;
use App\Http\Controllers\Api\V1\Workflows\TriggerWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowApprovalController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowContractSnapshotController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowContractTestRunController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowEnvironmentReleaseController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowShareController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowTemplateController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowVersionController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/folders')->as('folders.')->group(function (): void {
    Route::get('/', [FolderController::class, 'index'])->name('index');
    Route::post('/', [FolderController::class, 'store'])->name('store');
    Route::post('move-workflows', [FolderController::class, 'moveWorkflows'])->name('move-workflows');
    Route::put('{folder}', [FolderController::class, 'update'])->name('update');
    Route::delete('{folder}', [FolderController::class, 'destroy'])->name('destroy');
});

Route::prefix('{workspace}/tags')->as('tags.')->group(function (): void {
    Route::get('/', [TagController::class, 'index'])->name('index');
    Route::post('/', [TagController::class, 'store'])->name('store');
    Route::put('{tag}', [TagController::class, 'update'])->name('update');
    Route::delete('{tag}', [TagController::class, 'destroy'])->name('destroy');
});

Route::post('{workspace}/workflow-templates/{template}/instantiate', [WorkflowTemplateController::class, 'instantiate'])->name('workflow-templates.instantiate');
Route::post('{workspace}/shared-workflows/{token}/clone', CloneSharedWorkflowController::class)->name('shared-workflows.clone');

Route::prefix('{workspace}/workflows')->as('workflows.')->group(function (): void {
    Route::get('/', [WorkflowController::class, 'index'])->name('index');
    Route::post('/', [WorkflowController::class, 'store'])->name('store');
    Route::get('{workflow}', [WorkflowController::class, 'show'])->name('show');
    Route::put('{workflow}', [WorkflowController::class, 'update'])->name('update');
    Route::delete('{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
    Route::put('{workflow}/graph', SaveWorkflowGraphController::class)->name('graph');
    Route::post('{workflow}/publish', PublishWorkflowController::class)->name('publish');
    Route::post('{workflow}/trigger', TriggerWorkflowController::class)->name('trigger');
    Route::put('{workflow}/tags', SyncWorkflowTagsController::class)->name('tags.sync');

    Route::prefix('{workflow}/sticky-notes')->as('sticky-notes.')->group(function (): void {
        Route::get('/', [StickyNoteController::class, 'index'])->name('index');
        Route::post('/', [StickyNoteController::class, 'store'])->name('store');
        Route::put('{stickyNote}', [StickyNoteController::class, 'update'])->name('update');
        Route::delete('{stickyNote}', [StickyNoteController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('{workflow}/versions')->as('versions.')->group(function (): void {
        Route::get('/', [WorkflowVersionController::class, 'index'])->name('index');
        Route::get('{version}', [WorkflowVersionController::class, 'show'])->whereNumber('version')->name('show');
        Route::post('{version}/restore', [WorkflowVersionController::class, 'restore'])->whereNumber('version')->name('restore');
        Route::get('{from}/diff/{to}', [WorkflowVersionController::class, 'diff'])->whereNumber('from')->whereNumber('to')->name('diff');
    });

    Route::prefix('{workflow}/triggers')->as('triggers.')->group(function (): void {
        Route::get('/', [WorkflowTriggerController::class, 'index'])->name('index');
        Route::post('/', [WorkflowTriggerController::class, 'store'])->name('store');
        Route::put('{trigger}', [WorkflowTriggerController::class, 'update'])->name('update');
        Route::delete('{trigger}', [WorkflowTriggerController::class, 'destroy'])->name('destroy');
        Route::post('{trigger}/run', [WorkflowTriggerController::class, 'run'])->name('run');
        Route::post('{trigger}/rotate-token', [WorkflowTriggerController::class, 'rotateToken'])->name('rotate-token');
        Route::get('{trigger}/events', [TriggerEventController::class, 'index'])->name('events.index');
    });

    Route::prefix('{workflow}/replay-packs')->as('replay-packs.')->group(function (): void {
        Route::get('/', [RunReplayPackController::class, 'index'])->name('index');
        Route::post('/', [RunReplayPackController::class, 'store'])->name('store');
        Route::get('{replayPack}', [RunReplayPackController::class, 'show'])->name('show');
        Route::post('{replayPack}/replay', [RunReplayPackController::class, 'replay'])->name('replay');
    });

    Route::prefix('{workflow}/environment-releases')->as('environment-releases.')->group(function (): void {
        Route::get('/', [WorkflowEnvironmentReleaseController::class, 'index'])->name('index');
        Route::post('/', [WorkflowEnvironmentReleaseController::class, 'store'])->name('store');
    });

    Route::prefix('{workflow}/approvals')->as('approvals.')->group(function (): void {
        Route::get('/', [WorkflowApprovalController::class, 'index'])->name('index');
        Route::post('/', [WorkflowApprovalController::class, 'store'])->name('store');
        Route::post('{approval}/approve', [WorkflowApprovalController::class, 'approve'])->name('approve');
        Route::post('{approval}/reject', [WorkflowApprovalController::class, 'reject'])->name('reject');
    });

    Route::prefix('{workflow}/contract-snapshots')->as('contract-snapshots.')->group(function (): void {
        Route::get('/', [WorkflowContractSnapshotController::class, 'index'])->name('index');
        Route::post('/', [WorkflowContractSnapshotController::class, 'store'])->name('store');
        Route::get('{snapshot}', [WorkflowContractSnapshotController::class, 'show'])->name('show');

        Route::prefix('{snapshot}/test-runs')->as('test-runs.')->group(function (): void {
            Route::get('/', [WorkflowContractTestRunController::class, 'index'])->name('index');
            Route::post('/', [WorkflowContractTestRunController::class, 'store'])->name('store');
        });
    });

    Route::prefix('{workflow}/shares')->as('shares.')->group(function (): void {
        Route::get('/', [WorkflowShareController::class, 'index'])->name('index');
        Route::post('/', [WorkflowShareController::class, 'store'])->name('store');
        Route::delete('{share}', [WorkflowShareController::class, 'destroy'])->name('destroy');
    });
});
