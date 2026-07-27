<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreignId('node_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });

        $builtinNodesByStepType = DB::table('nodes')
            ->whereNull('workspace_id')
            ->where('is_custom', false)
            ->pluck('id', 'step_type');

        foreach ($builtinNodesByStepType as $stepType => $nodeId) {
            DB::table('workflow_steps')
                ->where('type', $stepType)
                ->whereNull('node_id')
                ->update(['node_id' => $nodeId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('node_id');
        });
    }
};
