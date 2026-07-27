<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No foreign keys — this table survives pruning of the runs/run_logs it was
     * copied from, so old logs stay queryable after the source rows are gone.
     */
    public function up(): void
    {
        Schema::create('archived_run_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('step_key')->nullable();
            $table->string('level', 20)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('logged_at');
            $table->timestamp('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_run_logs');
    }
};
