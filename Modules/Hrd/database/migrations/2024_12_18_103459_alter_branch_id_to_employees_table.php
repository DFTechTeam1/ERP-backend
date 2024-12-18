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
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedInteger('branch_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (checkForeignKey(tableName: 'employees', columnName: 'branch_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
    }
};
