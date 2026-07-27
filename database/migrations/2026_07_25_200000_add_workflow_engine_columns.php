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
        Schema::table('run_steps', function (Blueprint $table) {
            $table->unsignedInteger('attempt')->default(1)->after('usage');
            $table->unsignedInteger('max_attempts')->default(1)->after('attempt');
            $table->unsignedInteger('retry_delay_seconds')->default(0)->after('max_attempts');
            $table->unsignedInteger('loop_index')->nullable()->after('retry_delay_seconds');

            $table->unique(['run_id', 'key']);
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->foreignId('parent_run_id')->nullable()->after('workspace_id')
                ->constrained('runs')->nullOnDelete();
            $table->foreignId('parent_step_id')->nullable()->after('parent_run_id')
                ->constrained('run_steps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_step_id');
            $table->dropConstrainedForeignId('parent_run_id');
        });

        Schema::table('run_steps', function (Blueprint $table) {
            $table->dropUnique(['run_id', 'key']);
            $table->dropColumn(['attempt', 'max_attempts', 'retry_delay_seconds', 'loop_index']);
        });
    }
};
