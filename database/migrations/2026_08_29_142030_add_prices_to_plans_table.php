<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('laravel-subscriptions.tables.plans'), function (Blueprint $table): void {
            $table->json('prices')->nullable()->after('currency');
        });

        DB::table(config('laravel-subscriptions.tables.plans'))
            ->select(['id', 'currency', 'price'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    if (empty($plan->currency)) {
                        continue;
                    }

                    DB::table(config('laravel-subscriptions.tables.plans'))
                        ->where('id', $plan->id)
                        ->update([
                            'prices' => json_encode([
                                strtoupper((string) $plan->currency) => (float) $plan->price,
                            ], JSON_THROW_ON_ERROR),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table(config('laravel-subscriptions.tables.plans'), function (Blueprint $table): void {
            $table->dropColumn('prices');
        });
    }
};
