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
        Schema::create('trigger_types', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('mechanism');
            $table->json('preset_config')->nullable();
            $table->json('fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->morphs('triggerable');
            $table->string('type');
            $table->foreignId('trigger_type_id')->nullable()->constrained('trigger_types')->nullOnDelete();
            $table->json('config')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('triggers');
        Schema::dropIfExists('trigger_types');
    }
};
