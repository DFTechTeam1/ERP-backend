<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS get_event_class_distribution');
        DB::unprepared('
        CREATE PROCEDURE get_event_class_distribution()
        BEGIN
            SELECT 
                pc.name AS class_name,
                COUNT(p.id) AS project_count
            FROM 
                project_classes pc
            LEFT JOIN 
                projects p ON pc.id = p.project_class_id 
                AND YEAR(p.project_date) = YEAR(CURDATE())
            GROUP BY 
                pc.id, pc.name
            ORDER BY 
                pc.name;
        END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS get_event_class_distribution');
    }
};
