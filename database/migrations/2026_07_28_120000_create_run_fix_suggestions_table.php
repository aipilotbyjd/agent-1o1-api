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
        Schema::create('run_fix_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('step_key');
            $table->string('step_type', 50)->nullable();
            $table->text('diagnosis');
            // [{title, description, fix_config: {...}}]
            $table->json('suggestions');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['run_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_fix_suggestions');
    }
};
