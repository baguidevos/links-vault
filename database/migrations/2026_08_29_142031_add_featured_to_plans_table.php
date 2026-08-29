<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('laravel-subscriptions.tables.plans'), function (Blueprint $table): void {
            $table->boolean('featured')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table(config('laravel-subscriptions.tables.plans'), function (Blueprint $table): void {
            $table->dropColumn('featured');
        });
    }
};
