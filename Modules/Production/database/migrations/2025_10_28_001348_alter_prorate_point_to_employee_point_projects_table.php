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
        Schema::table('employee_point_projects', function (Blueprint $table) {
            $table->integer('prorate_point')->default(0)
                ->after('additional_point')
                ->comment('When project reached maximum projects, this column will be filled');
            $table->integer('calculated_prorate_point')->default(0)
                ->after('prorate_point')
                ->comment('When project reached maximum projects, this column will be filled');
            $table->integer('original_point')
                ->default(0)
                ->after('calculated_prorate_point')
                ->comment('1 task = 1 point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("employee_point_projects", function (Blueprint $table) {
            $table->dropColumn('prorate_point');
            $table->dropColumn('calculated_prorate_point');
            $table->dropColumn('original_point');
        });
    }
};
