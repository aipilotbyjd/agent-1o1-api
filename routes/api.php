<?php

use App\Http\Controllers\Api\V1\Agents\AgentController;
use App\Http\Controllers\Api\V1\Agents\AgentVersionController;
use App\Http\Controllers\Api\V1\Agents\ChatAgentController;
use App\Http\Controllers\Api\V1\Agents\SyncAgentToolsController;
use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResendVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\Session\IndexSessionController;
use App\Http\Controllers\Api\V1\Auth\Session\RevokeSessionController;
use App\Http\Controllers\Api\V1\Auth\Social\SocialCallbackController;
use App\Http\Controllers\Api\V1\Auth\Social\SocialRedirectController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\ConfirmTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\DisableTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\EnableTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\RecoveryCodesController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\RegenerateRecoveryCodesController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\VerifyTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\User\DeleteUserController;
use App\Http\Controllers\Api\V1\Auth\User\ShowUserController;
use App\Http\Controllers\Api\V1\Auth\User\UpdateUserController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\NotificationChannelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Runs\RunApprovalController;
use App\Http\Controllers\Api\V1\Runs\RunController;
use App\Http\Controllers\Api\V1\Tools\ToolController;
use App\Http\Controllers\Api\V1\Triggers\AgentTriggerController;
use App\Http\Controllers\Api\V1\Triggers\TriggerTypeController;
use App\Http\Controllers\Api\V1\Triggers\WebhookController;
use App\Http\Controllers\Api\V1\Triggers\WorkflowTriggerController;
use App\Http\Controllers\Api\V1\Workflows\PublishWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\TriggerWorkflowController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowGraphController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowVersionController;
use App\Http\Controllers\Api\V1\Workspaces\AcceptInvitationController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceMemberController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceUsageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('v1.')->group(function (): void {

    // Public — protected by the `signed` middleware, not auth:api.
    Route::get('auth/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:auth'])
        ->name('auth.verify-email');

    // Public — authenticated by the trigger's secret token.
    Route::post('hooks/{token}', WebhookController::class)
        ->middleware('throttle:60,1')
        ->name('hooks.trigger');

    // Guest — unauthenticated auth actions.
    Route::prefix('auth')->as('auth.')->middleware('throttle:auth')->group(function (): void {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->name('login');
        Route::post('refresh', RefreshTokenController::class)->name('refresh');

        Route::prefix('password')->as('password.')->group(function (): void {
            Route::post('forgot', ForgotPasswordController::class)->name('forgot');
            Route::post('reset', ResetPasswordController::class)->name('reset');
        });

        Route::prefix('2fa')->as('2fa.')->group(function (): void {
            Route::post('verify', VerifyTwoFactorController::class)->name('verify');
        });

        Route::prefix('social')->as('social.')->group(function (): void {
            Route::get('{provider}/redirect', SocialRedirectController::class)->name('redirect');
            Route::match(['GET', 'POST'], '{provider}/callback', SocialCallbackController::class)->name('callback');
        });
    });

    // Authenticated.
    Route::middleware('auth:api')->prefix('auth')->as('auth.')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');
        Route::post('logout-all', LogoutAllController::class)->name('logout-all');
        Route::post('verify-email/resend', ResendVerificationController::class)->name('verify-email.resend');
        Route::put('password/change', ChangePasswordController::class)->name('password.change');

        Route::get('user', ShowUserController::class)->name('user.show');
        Route::put('user', UpdateUserController::class)->name('user.update');
        Route::delete('user', DeleteUserController::class)->name('user.destroy');

        Route::prefix('2fa')->as('2fa.')->group(function (): void {
            Route::post('enable', EnableTwoFactorController::class)->name('enable');
            Route::post('confirm', ConfirmTwoFactorController::class)->name('confirm');
            Route::post('disable', DisableTwoFactorController::class)->name('disable');
            Route::get('recovery-codes', RecoveryCodesController::class)->name('recovery-codes');
            Route::post('recovery-codes/regenerate', RegenerateRecoveryCodesController::class)->name('recovery-codes.regenerate');
        });

        Route::get('sessions', IndexSessionController::class)->name('sessions.index');
        Route::delete('sessions/{id}', RevokeSessionController::class)->name('sessions.destroy');
    });

    Route::middleware(['auth:api', 'verified'])->group(function (): void {
        Route::get('trigger-types', TriggerTypeController::class)->name('trigger-types.index');

        Route::get('workspaces/invitations/{token}/accept', AcceptInvitationController::class)
            ->middleware('signed')
            ->name('workspaces.invitations.accept');

        Route::prefix('workspaces')->as('workspaces.')->group(function (): void {
            Route::get('/', [WorkspaceController::class, 'index'])->name('index');
            Route::post('/', [WorkspaceController::class, 'store'])->name('store');
            Route::get('{workspace}', [WorkspaceController::class, 'show'])->name('show');
            Route::put('{workspace}', [WorkspaceController::class, 'update'])->name('update');
            Route::delete('{workspace}', [WorkspaceController::class, 'destroy'])->name('destroy');
            Route::post('{workspace}/avatar', [WorkspaceController::class, 'updateAvatar'])->name('avatar.update');
            Route::get('{workspace}/invitations', [WorkspaceMemberController::class, 'invitations'])->name('invitations.index');

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

            Route::prefix('{workspace}/agents')->as('agents.')->group(function (): void {
                Route::get('/', [AgentController::class, 'index'])->name('index');
                Route::post('/', [AgentController::class, 'store'])->name('store');
                Route::get('{agent}', [AgentController::class, 'show'])->name('show');
                Route::put('{agent}', [AgentController::class, 'update'])->name('update');
                Route::delete('{agent}', [AgentController::class, 'destroy'])->name('destroy');
                Route::post('{agent}/chat', ChatAgentController::class)->name('chat');
                Route::put('{agent}/tools', SyncAgentToolsController::class)->name('tools.sync');

                Route::prefix('{agent}/versions')->as('versions.')->group(function (): void {
                    Route::get('/', [AgentVersionController::class, 'index'])->name('index');
                    Route::get('{version}', [AgentVersionController::class, 'show'])->whereNumber('version')->name('show');
                    Route::post('{version}/restore', [AgentVersionController::class, 'restore'])->whereNumber('version')->name('restore');
                });

                Route::prefix('{agent}/triggers')->as('triggers.')->group(function (): void {
                    Route::get('/', [AgentTriggerController::class, 'index'])->name('index');
                    Route::post('/', [AgentTriggerController::class, 'store'])->name('store');
                    Route::delete('{trigger}', [AgentTriggerController::class, 'destroy'])->name('destroy');
                });
            });

            Route::prefix('{workspace}/tools')->as('tools.')->group(function (): void {
                Route::get('/', [ToolController::class, 'index'])->name('index');
                Route::post('/', [ToolController::class, 'store'])->name('store');
                Route::get('{tool}', [ToolController::class, 'show'])->name('show');
                Route::put('{tool}', [ToolController::class, 'update'])->name('update');
                Route::delete('{tool}', [ToolController::class, 'destroy'])->name('destroy');
                Route::post('{tool}/test', [ToolController::class, 'test'])->name('test');
            });

            Route::prefix('{workspace}/workflows')->as('workflows.')->group(function (): void {
                Route::get('/', [WorkflowController::class, 'index'])->name('index');
                Route::post('/', [WorkflowController::class, 'store'])->name('store');
                Route::get('{workflow}', [WorkflowController::class, 'show'])->name('show');
                Route::put('{workflow}', [WorkflowController::class, 'update'])->name('update');
                Route::delete('{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
                Route::put('{workflow}/graph', WorkflowGraphController::class)->name('graph');
                Route::post('{workflow}/publish', PublishWorkflowController::class)->name('publish');
                Route::post('{workflow}/trigger', TriggerWorkflowController::class)->name('trigger');

                Route::prefix('{workflow}/versions')->as('versions.')->group(function (): void {
                    Route::get('/', [WorkflowVersionController::class, 'index'])->name('index');
                    Route::get('{version}', [WorkflowVersionController::class, 'show'])->whereNumber('version')->name('show');
                    Route::post('{version}/restore', [WorkflowVersionController::class, 'restore'])->whereNumber('version')->name('restore');
                    Route::get('{from}/diff/{to}', [WorkflowVersionController::class, 'diff'])->whereNumber('from')->whereNumber('to')->name('diff');
                });

                Route::prefix('{workflow}/triggers')->as('triggers.')->group(function (): void {
                    Route::get('/', [WorkflowTriggerController::class, 'index'])->name('index');
                    Route::post('/', [WorkflowTriggerController::class, 'store'])->name('store');
                    Route::delete('{trigger}', [WorkflowTriggerController::class, 'destroy'])->name('destroy');
                });
            });

            Route::prefix('{workspace}/runs')->as('runs.')->group(function (): void {
                Route::get('/', [RunController::class, 'index'])->name('index');
                Route::get('{run}', [RunController::class, 'show'])->name('show');
                Route::post('{run}/cancel', [RunController::class, 'cancel'])->name('cancel');
                Route::post('{run}/steps/{step}/approve', [RunApprovalController::class, 'approve'])->name('steps.approve');
                Route::post('{run}/steps/{step}/reject', [RunApprovalController::class, 'reject'])->name('steps.reject');
            });

            Route::get('{workspace}/usage', WorkspaceUsageController::class)->name('usage');

            Route::prefix('{workspace}/notification-preferences')->as('notification-preferences.')->group(function (): void {
                Route::get('/', [NotificationPreferenceController::class, 'index'])->name('index');
                Route::put('/', [NotificationPreferenceController::class, 'upsert'])->name('upsert');
            });
        });

        Route::prefix('notifications')->as('notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
            Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
            Route::delete('{notification}', [NotificationController::class, 'destroy'])->name('destroy');
        });
    });
});
