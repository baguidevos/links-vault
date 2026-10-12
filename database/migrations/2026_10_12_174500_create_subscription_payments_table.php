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
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained(config('laravel-subscriptions.tables.plans', 'plans'))->cascadeOnDelete();
            $table->string('period', 20)->default('monthly'); // monthly, yearly
            $table->string('session_id')->nullable()->index(); // ID session checkout SasPay
            $table->string('session_slug')->nullable();
            $table->string('payment_id')->nullable()->index(); // ID transaction SasPay
            $table->string('reference')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->decimal('charged', 12, 2)->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->string('status', 30)->default('PENDING')->index(); // PENDING, SUCCESS, FAILED
            $table->text('checkout_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
