<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A foreach loop step fans out one child run per item; this records which item a
     * given child came from, so iterations can be ordered and reported individually.
     */
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table): void {
            $table->unsignedInteger('loop_index')->nullable()->after('parent_step_id');
            $table->index(['parent_step_id', 'loop_index']);
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table): void {
            $table->dropIndex(['parent_step_id', 'loop_index']);
            $table->dropColumn('loop_index');
        });
    }
};
