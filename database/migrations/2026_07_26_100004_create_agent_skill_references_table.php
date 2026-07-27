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
        Schema::create('agent_skill_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('agent_skills')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['skill_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_skill_references');
    }
};
