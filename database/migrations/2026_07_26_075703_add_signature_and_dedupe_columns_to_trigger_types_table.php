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
        Schema::table('trigger_types', function (Blueprint $table): void {
            $table->string('signature_scheme')->nullable()->after('mechanism');
            $table->string('dedupe_header')->nullable()->after('signature_scheme');
            $table->string('dedupe_payload_path')->nullable()->after('dedupe_header');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trigger_types', function (Blueprint $table): void {
            $table->dropColumn(['signature_scheme', 'dedupe_header', 'dedupe_payload_path']);
        });
    }
};
