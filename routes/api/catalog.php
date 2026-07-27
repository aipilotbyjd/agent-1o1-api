<?php

use App\Http\Controllers\Api\V1\Agents\AgentTemplateController;
use App\Http\Controllers\Api\V1\Credentials\CredentialTypeController;
use App\Http\Controllers\Api\V1\Nodes\NodeCategoryController;
use App\Http\Controllers\Api\V1\Triggers\TriggerTypeController;
use App\Http\Controllers\Api\V1\Workflows\TemplateCollectionController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowTemplateController;
use Illuminate\Support\Facades\Route;

// Global, read-only catalogs — not workspace-scoped.
Route::get('trigger-types', TriggerTypeController::class)->name('trigger-types.index');
Route::get('node-categories', NodeCategoryController::class)->name('node-categories.index');
Route::get('credential-types', CredentialTypeController::class)->name('credential-types.index');
Route::get('agent-templates', [AgentTemplateController::class, 'index'])->name('agent-templates.index');

Route::get('workflow-templates', [WorkflowTemplateController::class, 'index'])->name('workflow-templates.index');
Route::get('workflow-templates/{key}', [WorkflowTemplateController::class, 'show'])->name('workflow-templates.show');
Route::get('template-collections', [TemplateCollectionController::class, 'index'])->name('template-collections.index');
Route::get('template-collections/{key}', [TemplateCollectionController::class, 'show'])->name('template-collections.show');
