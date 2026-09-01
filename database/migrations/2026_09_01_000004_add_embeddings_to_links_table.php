<?php

declare(strict_types=1);

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
            $table->longText('embedding')->nullable()->after('redirect_url');
            $table->string('embedding_model', 100)->nullable()->after('embedding');
            $table->timestamp('embedding_generated_at')->nullable()->after('embedding_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_model', 'embedding_generated_at']);
        });
    }
};
