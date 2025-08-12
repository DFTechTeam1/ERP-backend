<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS get_project_difference");
        DB::unprepared("
            CREATE PROCEDURE get_project_difference()
            BEGIN
                DECLARE total_current_year INT DEFAULT 0;
                DECLARE total_last_year INT DEFAULT 0;
                DECLARE number_diff INT DEFAULT 0;
                DECLARE percentage_diff DECIMAL(10,2) DEFAULT 0;

                SELECT COUNT(*) INTO total_current_year
                FROM projects
                WHERE YEAR(created_at) = YEAR(CURDATE());

                SELECT COUNT(*) INTO total_last_year
                FROM projects
                WHERE YEAR(created_at) = YEAR(CURDATE()) - 1;

                SET number_diff = total_current_year - total_last_year;

                IF total_last_year > 0 THEN
                    SET percentage_diff = (number_diff / total_last_year) * 100;
                ELSE
                    SET percentage_diff = 0;
                END IF;

                SELECT percentage_diff AS percentage_difference,
                       number_diff AS number_difference,
                       total_current_year AS total_event_current_year,
                       total_last_year AS total_event_last_year;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS get_project_difference");
    }
};
