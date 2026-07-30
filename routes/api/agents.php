<?php

use App\Http\Controllers\Api\V1\Agents\AgentAnalyticsController;
use App\Http\Controllers\Api\V1\Agents\AgentController;
use App\Http\Controllers\Api\V1\Agents\AgentEvalCaseController;
use App\Http\Controllers\Api\V1\Agents\AgentEvalRunController;
use App\Http\Controllers\Api\V1\Agents\AgentEvalSuiteController;
use App\Http\Controllers\Api\V1\Agents\AgentKnowledgeController;
use App\Http\Controllers\Api\V1\Agents\AgentMemoryController;
use App\Http\Controllers\Api\V1\Agents\AgentTemplateController;
use App\Http\Controllers\Api\V1\Agents\AgentVersionController;
use App\Http\Controllers\Api\V1\Agents\ChatAgentController;
use App\Http\Controllers\Api\V1\Agents\SyncAgentNodesController;
use App\Http\Controllers\Api\V1\Agents\SyncAgentSkillsController;
use App\Http\Controllers\Api\V1\Triggers\AgentTriggerController;
use App\Http\Controllers\Api\V1\Triggers\TriggerEventController;
use Illuminate\Support\Facades\Route;

Route::post('{workspace}/agent-templates/{template}/instantiate', [AgentTemplateController::class, 'instantiate'])->name('agent-templates.instantiate');

Route::prefix('{workspace}/agents')->as('agents.')->group(function (): void {
    Route::get('/', [AgentController::class, 'index'])->name('index');
    Route::post('/', [AgentController::class, 'store'])->name('store');
    Route::get('{agent}', [AgentController::class, 'show'])->name('show');
    Route::put('{agent}', [AgentController::class, 'update'])->name('update');
    Route::delete('{agent}', [AgentController::class, 'destroy'])->name('destroy');
    Route::post('{agent}/chat', ChatAgentController::class)->name('chat');
    Route::put('{agent}/nodes', SyncAgentNodesController::class)->name('nodes.sync');
    Route::put('{agent}/skills', SyncAgentSkillsController::class)->name('skills.sync');
    Route::get('{agent}/analytics', AgentAnalyticsController::class)->name('analytics');

    Route::prefix('{agent}/eval-suites')->as('eval-suites.')->group(function (): void {
        Route::get('/', [AgentEvalSuiteController::class, 'index'])->name('index');
        Route::post('/', [AgentEvalSuiteController::class, 'store'])->name('store');
        Route::get('{evalSuite}', [AgentEvalSuiteController::class, 'show'])->name('show');
        Route::delete('{evalSuite}', [AgentEvalSuiteController::class, 'destroy'])->name('destroy');
        Route::post('{evalSuite}/cases', [AgentEvalCaseController::class, 'store'])->name('cases.store');
        Route::delete('{evalSuite}/cases/{case}', [AgentEvalCaseController::class, 'destroy'])->name('cases.destroy');
        Route::get('{evalSuite}/runs', [AgentEvalRunController::class, 'index'])->name('runs.index');
        Route::post('{evalSuite}/run', [AgentEvalRunController::class, 'store'])->name('runs.store');
    });

    Route::prefix('{agent}/versions')->as('versions.')->group(function (): void {
        Route::get('/', [AgentVersionController::class, 'index'])->name('index');
        Route::get('{version}', [AgentVersionController::class, 'show'])->whereNumber('version')->name('show');
        Route::post('{version}/restore', [AgentVersionController::class, 'restore'])->whereNumber('version')->name('restore');
    });

    Route::prefix('{agent}/triggers')->as('triggers.')->group(function (): void {
        Route::get('/', [AgentTriggerController::class, 'index'])->name('index');
        Route::post('/', [AgentTriggerController::class, 'store'])->name('store');
        Route::put('{trigger}', [AgentTriggerController::class, 'update'])->name('update');
        Route::delete('{trigger}', [AgentTriggerController::class, 'destroy'])->name('destroy');
        Route::post('{trigger}/run', [AgentTriggerController::class, 'run'])->name('run');
        Route::post('{trigger}/rotate-token', [AgentTriggerController::class, 'rotateToken'])->name('rotate-token');
        Route::get('{trigger}/events', [TriggerEventController::class, 'index'])->name('events.index');
    });

    Route::prefix('{agent}/knowledge')->as('knowledge.')->group(function (): void {
        Route::get('/', [AgentKnowledgeController::class, 'index'])->name('index');
        Route::post('/', [AgentKnowledgeController::class, 'store'])->name('store');
        Route::put('{knowledge}', [AgentKnowledgeController::class, 'update'])->name('update');
        Route::delete('{knowledge}', [AgentKnowledgeController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('{agent}/memories')->as('memories.')->group(function (): void {
        Route::get('/', [AgentMemoryController::class, 'index'])->name('index');
        Route::post('/', [AgentMemoryController::class, 'store'])->name('store');
        Route::put('{memory}', [AgentMemoryController::class, 'update'])->name('update');
        Route::delete('{memory}', [AgentMemoryController::class, 'destroy'])->name('destroy');
    });
});
