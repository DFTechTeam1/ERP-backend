<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Greatday returns these on /employees but the staging table never kept them, so the
     * "Sync work information" dialog had nothing to auto-fill its personal and bank fields
     * from. All nullable and additive — existing rows backfill on the next Greatday refresh.
     */
    public function up(): void
    {
        Schema::table('out_of_sync_employees', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('last_name');
            $table->string('id_number', 50)->nullable()->after('nickname');
            $table->tinyInteger('gender')->nullable()->after('id_number');
            $table->date('birth_date')->nullable()->after('gender');
            $table->string('birth_place')->nullable()->after('birth_date');
            $table->string('grade_code', 100)->nullable()->after('cost_center_code');
            $table->string('bank_code', 100)->nullable()->after('grade_code');
            $table->string('bank_account', 100)->nullable()->after('bank_code');
            $table->string('bank_account_name')->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('out_of_sync_employees', function (Blueprint $table) {
            $table->dropColumn([
                'nickname',
                'id_number',
                'gender',
                'birth_date',
                'birth_place',
                'grade_code',
                'bank_code',
                'bank_account',
                'bank_account_name',
            ]);
        });
    }
};
