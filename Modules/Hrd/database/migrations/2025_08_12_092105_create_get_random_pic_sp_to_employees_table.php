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
        DB::unprepared("
        CREATE FUNCTION `get_random_project_pic`() RETURNS varchar(255) CHARSET utf8mb4
            DETERMINISTIC
        BEGIN
            DECLARE OUTPUTUID VARCHAR(255);
            
            SELECT uid INTO OUTPUTUID
            FROM employees
            WHERE id = (
                SELECT DISTINCT(pic_id)
                FROM project_person_in_charges
                LIMIT 1
            ) and status NOT IN (0,5,6,7,8);
            
            RETURN OUTPUTUID;
        END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS get_random_project_pic");
    }
};
