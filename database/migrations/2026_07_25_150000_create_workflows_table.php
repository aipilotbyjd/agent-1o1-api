<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type');
            $table->json('config')->nullable();
            $table->json('position')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'key']);
        });

        Schema::create('workflow_step_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->string('condition')->nullable();
            $table->timestamps();

            $table->unique(['from_step_id', 'to_step_id', 'condition']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_step_edges');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
