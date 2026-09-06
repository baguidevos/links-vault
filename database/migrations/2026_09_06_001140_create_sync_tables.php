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
        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 50)->comment('link, folder, category, tag');
            $table->uuid('entity_uuid');
            $table->timestamp('deleted_at');

            $table->index(['team_id', 'deleted_at']);
            $table->index('entity_uuid');
        });

        Schema::create('sync_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('server_url')->nullable();
            $table->text('api_token')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 30)->default('idle');
            $table->boolean('auto_sync_enabled')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_settings');
        Schema::dropIfExists('sync_tombstones');
    }
};
