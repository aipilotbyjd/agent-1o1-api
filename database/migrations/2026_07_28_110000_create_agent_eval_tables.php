<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent eval/testing framework: a saved suite of test cases (input + assertions)
 * that can be run against an agent to produce a pass/fail report before
 * publishing or after editing instructions.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_eval_suites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_eval_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_id')->constrained('agent_eval_suites')->cascadeOnDelete();
            $table->string('name');
            $table->longText('input');
            // How to grade the agent's answer: one or more assertions, e.g.
            // [{"type":"contains","value":"refund"},{"type":"llm_rubric","value":"Politely declines"}]
            $table->json('assertions');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('agent_eval_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_id')->constrained('agent_eval_suites')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('passed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            // Per-case results: [{case_id, name, passed, output, failures:[...]}]
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['suite_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_eval_runs');
        Schema::dropIfExists('agent_eval_cases');
        Schema::dropIfExists('agent_eval_suites');
    }
};
