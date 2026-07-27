<?php

use App\Http\Controllers\Api\V1\Agents\AgentSkillController;
use App\Http\Controllers\Api\V1\Agents\AgentSkillReferenceController;
use App\Http\Controllers\Api\V1\Agents\AgentSkillScriptController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/agent-skills')->as('agent-skills.')->group(function (): void {
    Route::get('/', [AgentSkillController::class, 'index'])->name('index');
    Route::post('/', [AgentSkillController::class, 'store'])->name('store');
    Route::get('{skill}', [AgentSkillController::class, 'show'])->name('show');
    Route::put('{skill}', [AgentSkillController::class, 'update'])->name('update');
    Route::delete('{skill}', [AgentSkillController::class, 'destroy'])->name('destroy');

    Route::prefix('{skill}/references')->as('references.')->group(function (): void {
        Route::get('/', [AgentSkillReferenceController::class, 'index'])->name('index');
        Route::post('/', [AgentSkillReferenceController::class, 'store'])->name('store');
        Route::put('{reference}', [AgentSkillReferenceController::class, 'update'])->name('update');
        Route::delete('{reference}', [AgentSkillReferenceController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('{skill}/scripts')->as('scripts.')->group(function (): void {
        Route::get('/', [AgentSkillScriptController::class, 'index'])->name('index');
        Route::post('/', [AgentSkillScriptController::class, 'store'])->name('store');
        Route::put('{script}', [AgentSkillScriptController::class, 'update'])->name('update');
        Route::delete('{script}', [AgentSkillScriptController::class, 'destroy'])->name('destroy');
    });
});
