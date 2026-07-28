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
        Schema::create('sticky_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content')->nullable();
            $table->string('color', 7)->nullable();
            $table->decimal('position_x', 10, 2)->default(0);
            $table->decimal('position_y', 10, 2)->default(0);
            $table->decimal('width', 10, 2)->default(200);
            $table->decimal('height', 10, 2)->default(120);
            $table->unsignedInteger('z_index')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sticky_notes');
    }
};
