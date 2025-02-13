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
        if (checkForeignKey('indonesia_cities', 'province_code')) {
            Schema::table('indonesia_cities', function (Blueprint $table) {
                $table->dropForeign(['province_code']);
            });
        }

        Schema::table('indonesia_provinces', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });

        Schema::table('indonesia_cities', function (Blueprint $table) {
            $table->string('province_code', 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indonesia_provinces', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });

        Schema::table('indonesia_cities', function (Blueprint $table) {
            $table->string('province_code', 10)->change();
        });
    }
};
