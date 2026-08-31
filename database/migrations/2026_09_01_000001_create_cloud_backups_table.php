<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 50)->default('local'); // local, s3, google_drive, dropbox
            $table->string('filename');
            $table->unsignedBigInteger('file_size')->default(0); // in bytes
            $table->string('remote_path')->nullable();
            $table->string('remote_file_id')->nullable();
            $table->unsignedInteger('links_count')->default(0);
            $table->unsignedInteger('folders_count')->default(0);
            $table->unsignedInteger('tags_count')->default(0);
            $table->string('status', 30)->default('completed'); // completed, failed, in_progress
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['team_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_backups');
    }
};
