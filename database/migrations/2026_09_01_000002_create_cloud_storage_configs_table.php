<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_storage_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50); // s3, google_drive, dropbox, local
            $table->boolean('is_active')->default(false);
            $table->boolean('auto_backup_enabled')->default(false);
            $table->string('frequency', 30)->default('weekly'); // daily, weekly, monthly
            $table->unsignedInteger('retention_count')->default(10);
            $table->text('credentials')->nullable(); // encrypted JSON credentials
            $table->timestamp('last_backup_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_storage_configs');
    }
};
