<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->text('url')->change();

            if (DB::getDriverName() === 'mysql') {
                $table->dropFullText('ft_search');
                $table->fullText(['title', 'description', 'ai_summary'], 'ft_search');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $table->dropFullText('ft_search');
                $table->fullText(['title', 'description', 'ai_summary', 'url'], 'ft_search');
            }

            $table->string('url', 2083)->change();
        });
    }
};
