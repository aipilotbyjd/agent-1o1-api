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
        Schema::create('node_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50);
            $table->string('color', 20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('kind', 20)->default('core');
            $table->timestamps();

            $table->index('kind');
        });

        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('node_categories')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('step_type', 30);
            $table->string('type', 100)->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 50);
            $table->string('color', 20);
            $table->json('config_schema');
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->string('credential_type', 100)->nullable();
            $table->decimal('cost_hint_usd', 10, 4)->nullable();
            $table->unsignedInteger('latency_hint_ms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->string('docs_url', 500)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_custom']);
        });

        Schema::create('pinned_node_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();

            $table->unique(['workflow_id', 'node_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinned_node_data');
        Schema::dropIfExists('nodes');
        Schema::dropIfExists('node_categories');
    }
};
