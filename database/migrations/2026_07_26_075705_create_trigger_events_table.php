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
        Schema::create('trigger_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trigger_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->boolean('matched')->default(true);
            $table->foreignId('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->text('payload_snippet')->nullable();
            $table->json('headers')->nullable();
            $table->text('error')->nullable();
            $table->string('delivery_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trigger_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trigger_events');
    }
};
