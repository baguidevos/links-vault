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
        Schema::table('links', function (Blueprint $table) {
            $table->unsignedSmallInteger('http_status')->nullable()->after('last_visited_at')->comment('Last HTTP response status code (e.g. 200, 301, 404, 500)');
            $table->string('health_status', 30)->default('unknown')->after('http_status')->comment('healthy, redirect, broken, unknown');
            $table->timestamp('last_health_checked_at')->nullable()->after('health_status')->comment('When the link was last verified');
            $table->string('health_error', 500)->nullable()->after('last_health_checked_at')->comment('Error message if broken or timed out');
            $table->string('redirect_url', 2083)->nullable()->after('health_error')->comment('Target URL if redirected');

            $table->index(['team_id', 'health_status']);
            $table->index('health_status');
            $table->index('last_health_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'health_status']);
            $table->dropIndex(['health_status']);
            $table->dropIndex(['last_health_checked_at']);

            $table->dropColumn([
                'http_status',
                'health_status',
                'last_health_checked_at',
                'health_error',
                'redirect_url',
            ]);
        });
    }
};
