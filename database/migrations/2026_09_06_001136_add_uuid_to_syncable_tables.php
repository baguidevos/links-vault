<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<string>
     */
    protected array $tables = ['teams', 'categories', 'folders', 'tags', 'links'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable()->after('id')->index();
                });

                // Remplissage rétroactif des enregistrements existants
                DB::table($tableName)->whereNull('uuid')->cursor()->each(function ($record) use ($tableName) {
                    DB::table($tableName)->where('id', $record->id)->update([
                        'uuid' => (string) Str::uuid(),
                    ]);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
