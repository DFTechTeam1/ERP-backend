<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $procedure = "
        DROP PROCEDURE IF EXISTS get_highest_event_number_for_pic;
        
        CREATE PROCEDURE get_highest_event_number_for_pic(IN status_filter VARCHAR(100))
        BEGIN
            DECLARE where_clause VARCHAR(1000);
            
            -- Build the WHERE clause for status filtering
            IF status_filter IS NOT NULL AND status_filter != '' THEN
                SET where_clause = CONCAT('AND e.status NOT IN (', status_filter, ')');
            ELSE
                SET where_clause = '';
            END IF;
            
            -- Create a temporary table to store the top_pic_id
            DROP TEMPORARY TABLE IF EXISTS temp_top_pic;
            CREATE TEMPORARY TABLE temp_top_pic (id INT);
            
            -- First get the top PIC ID with status filter
            SET @sql = CONCAT('
                INSERT INTO temp_top_pic
                SELECT pic_id
                FROM project_person_in_charges t
                JOIN employees e ON t.pic_id = e.id
                WHERE t.created_at >= DATE_FORMAT(CURRENT_DATE, \"%Y-01-01\")
                AND t.created_at <= CURRENT_DATE
                ', where_clause, '
                GROUP BY pic_id
                ORDER BY COUNT(DISTINCT project_id) DESC
                LIMIT 1;
            ');
            
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            
            -- Then get all projects for this PIC in current year with the same status filter
            SET @sql = CONCAT('
                SELECT 
                    e.uid AS pic_id,
                    e.name AS pic_name,
                    COUNT(DISTINCT t.project_id) AS project_count,
                    GROUP_CONCAT(DISTINCT t.project_id) AS project_ids
                FROM 
                    project_person_in_charges t
                JOIN 
                    employees e ON t.pic_id = e.id
                WHERE 
                    t.pic_id = (SELECT COALESCE(id, 0) FROM temp_top_pic LIMIT 1)
                    AND t.created_at >= DATE_FORMAT(CURRENT_DATE, \"%Y-01-01\")
                    AND t.created_at <= CURRENT_DATE
                    ', where_clause, '
                GROUP BY 
                    e.id, e.name;
            ');
            
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            
            -- Clean up
            DROP TEMPORARY TABLE IF EXISTS temp_top_pic;
        END
        ";

        DB::unprepared('DROP PROCEDURE IF EXISTS get_highest_event_number_for_pic');
        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS get_highest_event_number_for_pic');
    }
};
