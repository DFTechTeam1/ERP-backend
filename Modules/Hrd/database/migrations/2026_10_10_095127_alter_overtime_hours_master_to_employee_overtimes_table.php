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
        Schema::table('employee_overtimes', function (Blueprint $table) {
            $table->decimal('master_overtime_hours', 12, 4)->default(0)->after('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_overtimes', function (Blueprint $table) {
            $table->dropColumn('master_overtime_hours');
        });
    }
};
