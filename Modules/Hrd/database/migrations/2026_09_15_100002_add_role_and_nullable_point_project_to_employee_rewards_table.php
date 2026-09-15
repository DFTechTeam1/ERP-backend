<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PM and VJ rewards are fixed per class (not point-based), so they share the employee_rewards
     * table via a `role` discriminator and have no employee_point_project, which must therefore
     * become nullable.
     */
    public function up(): void
    {
        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->string('role')->default('production')->after('project_class_name');
        });

        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->dropForeign(['employee_point_project_id']);
        });

        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_point_project_id')->nullable()->change();
            $table->foreign('employee_point_project_id')->references('id')->on('employee_point_projects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->dropForeign(['employee_point_project_id']);
        });

        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_point_project_id')->nullable(false)->change();
            $table->foreign('employee_point_project_id')->references('id')->on('employee_point_projects');
        });

        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
