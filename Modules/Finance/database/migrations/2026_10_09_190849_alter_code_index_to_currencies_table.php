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
        Schema::table('currencies', function (Blueprint $table) {
            if (! Schema::hasIndex(table: 'currencies', index: ['code'], type: 'unique')) {
                $table->unique('code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (Schema::hasIndex(table: 'currencies', index: ['code'], type: 'unique')) {
                $table->dropUnique('currencies_code_unique');
            }
        });
    }
};
