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
        Schema::table('project_classes', function (Blueprint $table) {
            $table->tinyInteger('base_point')->default(0)->after('maximal_point');
            $table->tinyInteger('point_2_team')->default(0)->after('base_point');
            $table->tinyInteger('point_3_team')->default(0)->after('point_2_team');
            $table->tinyInteger('point_4_team')->default(0)->after('point_3_team');
            $table->tinyInteger('point_5_team')->default(0)->after('point_4_team');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_classes', function (Blueprint $table) {
            $table->dropColumn([
                'base_point',
                'point_2_team',
                'point_3_team',
                'point_4_team',
                'point_5_team',
            ]);
        });
    }
};
