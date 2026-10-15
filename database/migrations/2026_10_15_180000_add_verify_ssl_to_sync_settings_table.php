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
        Schema::table('sync_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('sync_settings', 'verify_ssl')) {
                $table->boolean('verify_ssl')->default(true)->after('auto_sync_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sync_settings', 'verify_ssl')) {
                $table->dropColumn('verify_ssl');
            }
        });
    }
};
