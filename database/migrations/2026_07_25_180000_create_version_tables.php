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
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('graph');
            $table->string('notes', 500)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workflow_id', 'version']);
        });

        Schema::create('agent_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agent_id', 'version']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('status')
                ->constrained('workflow_versions')->nullOnDelete();
            $table->boolean('has_unpublished_changes')->default(false)->after('current_version_id');
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->foreignId('workflow_version_id')->nullable()->after('runnable_id')
                ->constrained('workflow_versions')->nullOnDelete();
            $table->unsignedInteger('agent_version')->nullable()->after('workflow_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_version_id');
            $table->dropColumn('agent_version');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn('has_unpublished_changes');
        });

        Schema::dropIfExists('agent_versions');
        Schema::dropIfExists('workflow_versions');
    }
};
