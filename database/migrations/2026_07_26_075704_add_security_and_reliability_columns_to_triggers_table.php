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
        Schema::table('triggers', function (Blueprint $table): void {
            $table->text('signing_secret')->nullable()->after('token');
            $table->unsignedInteger('consecutive_failure_count')->default(0)->after('last_run_at');
            $table->foreignId('credential_id')->nullable()->after('trigger_type_id')
                ->constrained()->nullOnDelete();
            $table->json('poll_cursor')->nullable()->after('credential_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credential_id');
            $table->dropColumn(['signing_secret', 'consecutive_failure_count', 'poll_cursor']);
        });
    }
};
