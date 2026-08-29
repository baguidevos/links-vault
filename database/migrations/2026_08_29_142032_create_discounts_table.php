<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('subbase.tables.discounts', 'discounts');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->index();
            $table->string('type'); // percentage, fixed
            $table->decimal('value', 15, 2);
            $table->string('currency', 10)->nullable();
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applies_to')->nullable(); // which entities: plans, features
            $table->json('applicable_plans')->nullable(); // array of plan IDs
            $table->json('applicable_features')->nullable(); // array of feature IDs
            $table->unsignedSmallInteger('priority')->default(0);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Pivot table for discount-subscription (applied discounts)
        Schema::create('discount_subscription', function (Blueprint $table) use ($tableName): void {
            $table->id();
            $table->foreignId('discount_id')->constrained($tableName)->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained(config('laravel-subscriptions.tables.subscriptions', 'subscriptions'))->cascadeOnDelete();
            $table->decimal('discounted_amount', 15, 2)->nullable();
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['discount_id', 'subscription_id']);
        });

        // Pivot table for discount-plan relationship
        Schema::create('discount_plan', function (Blueprint $table) use ($tableName): void {
            $table->id();
            $table->foreignId('discount_id')->constrained($tableName)->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained(config('laravel-subscriptions.tables.plans', 'plans'))->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['discount_id', 'plan_id']);
        });

        // Pivot table for discount-feature relationship
        Schema::create('discount_feature', function (Blueprint $table) use ($tableName): void {
            $table->id();
            $table->foreignId('discount_id')->constrained($tableName)->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained(config('subbase.tables.features', 'features'))->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['discount_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_feature');
        Schema::dropIfExists('discount_plan');
        Schema::dropIfExists('discount_subscription');
        Schema::dropIfExists(config('subbase.tables.discounts', 'discounts'));
    }
};
