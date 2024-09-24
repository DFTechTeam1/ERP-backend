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
        Schema::table('transfer_team_members', function (Blueprint $table) {
            $table->boolean('is_entertainment')->default(false);

            $table->integer('employee_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfer_team_members', function (Blueprint $table) {
            $table->dropColumn('is_entertainment');

            $table->integer('employee_id')->change();
        });
    }
};
