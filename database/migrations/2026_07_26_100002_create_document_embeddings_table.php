<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The embedding vector is stored as JSON for driver portability (sqlite locally,
     * Postgres in production). Once pgvector is approved as a dependency, a follow-up
     * migration can add a native `vector` column + ivfflat index on Postgres.
     */
    public function up(): void
    {
        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('collection')->default('default');
            $table->string('source')->nullable();
            $table->text('chunk_text');
            $table->json('embedding');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'collection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_embeddings');
    }
};
