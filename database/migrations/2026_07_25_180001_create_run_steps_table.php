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
        Schema::create('run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->json('usage')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->unsignedInteger('retry_delay_seconds')->default(0);
            $table->unsignedInteger('loop_index')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('callback_token')->nullable()->unique();
            $table->timestamp('callback_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'key']);
            $table->index(['run_id', 'status']);
            $table->index('callback_expires_at');
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->foreign('parent_step_id')
                ->references('id')->on('run_steps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropForeign(['parent_step_id']);
        });

        Schema::dropIfExists('run_steps');
    }
};
