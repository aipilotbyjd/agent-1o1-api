<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire the `tools` table in favour of workspace-authored `nodes` rows.
     *
     * A tool was only ever a node with its config already filled in, so custom nodes
     * gain the two columns a tool row carried that the catalog lacked — its own stored
     * `config` (url, method, headers) and a `credential_id` — and agents now attach
     * nodes instead, with the per-attachment binding that makes a connector safe to
     * hand to a model.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->json('config')->nullable()->after('config_schema');
            $table->foreignId('credential_id')->nullable()->after('credential_type')
                ->constrained()->nullOnDelete();
        });

        Schema::create('agent_node', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();

            // Config values bound at attach time. These are merged over whatever the
            // model supplies, so a credential or a fixed channel can never be chosen
            // by the LLM.
            $table->json('config')->nullable();

            // The config fields the model is allowed to fill. Null means "every field
            // that isn't already bound".
            $table->json('exposed_fields')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'node_id']);
        });

        Schema::dropIfExists('agent_tool');
        Schema::dropIfExists('tools');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_node');

        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credential_id');
            $table->dropColumn('config');
        });
    }
};
