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
        Schema::create('credential_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('auth_type', 30);
            $table->string('color', 20);
            $table->string('icon', 50);
            $table->string('docs_url', 500)->nullable();
            $table->json('fields');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type', 100);
            $table->text('data');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'type']);
        });

        Schema::create('variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('key', 100);
            $table->text('value');
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->foreignId('credential_id')->nullable()->after('workspace_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credential_id');
        });

        Schema::dropIfExists('variables');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('credential_types');
    }
};
