<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('run_steps', function (Blueprint $table): void {
            // A wait step's callback URL is public, so the token *is* the credential —
            // it has to be unique and unguessable rather than just the step's id.
            $table->string('callback_token')->nullable()->unique()->after('loop_index');
            $table->timestamp('callback_expires_at')->nullable()->after('callback_token');

            $table->index('callback_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('run_steps', function (Blueprint $table): void {
            $table->dropIndex(['callback_expires_at']);
            $table->dropUnique(['callback_token']);
            $table->dropColumn(['callback_token', 'callback_expires_at']);
        });
    }
};
