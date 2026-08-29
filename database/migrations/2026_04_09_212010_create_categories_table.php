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
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->string('color', 7)->default('#6B7280')->comment('Hex color');
                $table->string('icon', 50)->nullable()->comment('Heroicon name');
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'slug']);
                $table->index(['user_id', 'sort_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
