<?php

use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->as('notifications.')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
    Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
    Route::delete('{notification}', [NotificationController::class, 'destroy'])->name('destroy');
});
