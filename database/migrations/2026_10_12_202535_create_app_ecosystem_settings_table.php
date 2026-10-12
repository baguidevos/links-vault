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
        Schema::create('app_ecosystem_settings', function (Blueprint $table) {
            $table->id();

            // Desktop App
            $table->text('desktop_windows_url')->nullable();
            $table->text('desktop_mac_url')->nullable();
            $table->text('desktop_linux_url')->nullable();
            $table->string('desktop_version')->default('1.1.0');
            $table->boolean('desktop_is_active')->default(true);
            $table->text('desktop_release_notes')->nullable();

            // Browser Extensions
            $table->text('extension_chrome_url')->nullable();
            $table->boolean('extension_chrome_is_active')->default(true);
            $table->string('extension_chrome_version')->default('1.0.0');
            $table->text('extension_firefox_url')->nullable();
            $table->boolean('extension_firefox_is_active')->default(false);
            $table->string('extension_firefox_version')->default('1.0.0');

            // Mobile Apps
            $table->text('mobile_android_url')->nullable();
            $table->boolean('mobile_android_is_active')->default(false);
            $table->string('mobile_android_version')->default('1.0.0');
            $table->text('mobile_ios_url')->nullable();
            $table->boolean('mobile_ios_is_active')->default(false);
            $table->string('mobile_ios_version')->default('1.0.0');
            $table->text('mobile_notice')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_ecosystem_settings');
    }
};
