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
        Schema::create('triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->morphs('triggerable');
            $table->string('type');
            $table->foreignId('trigger_type_id')->nullable()->constrained('trigger_types')->nullOnDelete();
            $table->json('config')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->text('signing_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('credential_id')->nullable()->constrained()->nullOnDelete();
            $table->json('poll_cursor')->nullable();
            $table->unsignedInteger('consecutive_failure_count')->default(0);
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
    }
};
