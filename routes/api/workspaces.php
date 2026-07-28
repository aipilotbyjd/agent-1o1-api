<?php

use App\Http\Controllers\Api\V1\Notifications\NotificationChannelController;
use App\Http\Controllers\Api\V1\Notifications\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Workspaces\AiGenerationLogController;
use App\Http\Controllers\Api\V1\Workspaces\DocumentEmbeddingController;
use App\Http\Controllers\Api\V1\Workspaces\GitSyncConfigController;
use App\Http\Controllers\Api\V1\Workspaces\LogStreamingConfigController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceDashboardController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceEnvironmentController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceMemberController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceUsageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WorkspaceController::class, 'index'])->name('index');
Route::post('/', [WorkspaceController::class, 'store'])->name('store');

// Everything below is scoped to a {workspace}; workspace.context resolves the workspace
// and the caller's role (or 404/403s) before any controller or form request runs.
Route::middleware('workspace.context')->group(function (): void {
    Route::get('{workspace}', [WorkspaceController::class, 'show'])->name('show');
    Route::put('{workspace}', [WorkspaceController::class, 'update'])->name('update');
    Route::delete('{workspace}', [WorkspaceController::class, 'destroy'])->name('destroy');
    Route::post('{workspace}/avatar', [WorkspaceController::class, 'updateAvatar'])->name('avatar.update');
    Route::get('{workspace}/invitations', [WorkspaceMemberController::class, 'invitations'])->name('invitations.index');
    Route::get('{workspace}/usage', WorkspaceUsageController::class)->name('usage');
    Route::get('{workspace}/ai-generation-logs', [AiGenerationLogController::class, 'index'])->name('ai-generation-logs.index');
    Route::get('{workspace}/dashboard', WorkspaceDashboardController::class)->name('dashboard');

    Route::prefix('{workspace}/log-streaming-configs')->as('log-streaming-configs.')->group(function (): void {
        Route::get('/', [LogStreamingConfigController::class, 'index'])->name('index');
        Route::post('/', [LogStreamingConfigController::class, 'store'])->name('store');
        Route::put('{logStreamingConfig}', [LogStreamingConfigController::class, 'update'])->name('update');
        Route::delete('{logStreamingConfig}', [LogStreamingConfigController::class, 'destroy'])->name('destroy');
        Route::post('{logStreamingConfig}/test', [LogStreamingConfigController::class, 'test'])->name('test');
    });

    Route::prefix('{workspace}/members')->as('members.')->group(function (): void {
        Route::get('/', [WorkspaceMemberController::class, 'index'])->name('index');
        Route::post('invite', [WorkspaceMemberController::class, 'invite'])->name('invite');
        Route::delete('leave', [WorkspaceMemberController::class, 'leave'])->name('leave');
        Route::patch('{member}', [WorkspaceMemberController::class, 'updateRole'])->name('update-role');
        Route::delete('{member}', [WorkspaceMemberController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('{workspace}/notification-channels')->as('notification-channels.')->group(function (): void {
        Route::get('/', [NotificationChannelController::class, 'index'])->name('index');
        Route::post('/', [NotificationChannelController::class, 'store'])->name('store');
        Route::put('{notificationChannel}', [NotificationChannelController::class, 'update'])->name('update');
        Route::delete('{notificationChannel}', [NotificationChannelController::class, 'destroy'])->name('destroy');
        Route::post('{notificationChannel}/test', [NotificationChannelController::class, 'test'])->name('test');
    });

    Route::prefix('{workspace}/notification-preferences')->as('notification-preferences.')->group(function (): void {
        Route::get('/', [NotificationPreferenceController::class, 'index'])->name('index');
        Route::put('/', [NotificationPreferenceController::class, 'upsert'])->name('upsert');
    });

    Route::prefix('{workspace}/environments')->as('environments.')->group(function (): void {
        Route::get('/', [WorkspaceEnvironmentController::class, 'index'])->name('index');
        Route::post('/', [WorkspaceEnvironmentController::class, 'store'])->name('store');
        Route::put('{environment}', [WorkspaceEnvironmentController::class, 'update'])->name('update');
        Route::delete('{environment}', [WorkspaceEnvironmentController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('{workspace}/git-sync-configs')->as('git-sync-configs.')->group(function (): void {
        Route::get('/', [GitSyncConfigController::class, 'index'])->name('index');
        Route::post('/', [GitSyncConfigController::class, 'store'])->name('store');
        Route::put('{gitSyncConfig}', [GitSyncConfigController::class, 'update'])->name('update');
        Route::delete('{gitSyncConfig}', [GitSyncConfigController::class, 'destroy'])->name('destroy');
        Route::post('{gitSyncConfig}/sync', [GitSyncConfigController::class, 'sync'])->name('sync');
    });

    Route::prefix('{workspace}/document-embeddings')->as('document-embeddings.')->group(function (): void {
        Route::get('/', [DocumentEmbeddingController::class, 'index'])->name('index');
        Route::post('/', [DocumentEmbeddingController::class, 'store'])->name('store');
        Route::delete('{documentEmbedding}', [DocumentEmbeddingController::class, 'destroy'])->name('destroy');
    });
});
