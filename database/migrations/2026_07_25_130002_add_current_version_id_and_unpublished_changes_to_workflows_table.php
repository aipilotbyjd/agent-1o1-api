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
        Schema::table('workflows', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('status')
                ->constrained('workflow_versions')->nullOnDelete();
            $table->boolean('has_unpublished_changes')->default(false)->after('current_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn('has_unpublished_changes');
        });
    }
};
