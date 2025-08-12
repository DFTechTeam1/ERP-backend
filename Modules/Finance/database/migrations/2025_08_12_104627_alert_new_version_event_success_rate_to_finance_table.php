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
        DB::unprepared("DROP PROCEDURE IF EXISTS get_event_success_rate");
        DB::unprepared("
            CREATE PROCEDURE get_event_success_rate()
            BEGIN
                DECLARE total_events INT DEFAULT 0;
                DECLARE total_final INT DEFAULT 0;
                DECLARE total_cancel INT DEFAULT 0;
                DECLARE decided_events INT DEFAULT 0;
                DECLARE success_rate DECIMAL(10,2) DEFAULT 0;
                DECLARE fail_rate DECIMAL(10,2) DEFAULT 0;

                SELECT COUNT(*) INTO total_events
                FROM project_deals
                WHERE YEAR(project_date) = YEAR(CURDATE());

                SELECT COUNT(*) INTO total_final
                FROM project_deals
                WHERE status = 1
                AND YEAR(project_date) = YEAR(CURDATE());

                SELECT COUNT(*) INTO total_cancel
                FROM project_deals
                WHERE status = 3
                AND YEAR(project_date) = YEAR(CURDATE());

                SET decided_events = total_final + total_cancel;

                -- Calculate rates based on TOTAL EVENTS
                IF total_events > 0 THEN
                    SET success_rate = (total_final / total_events) * 100;
                    SET fail_rate = (total_cancel / total_events) * 100;
                ELSE
                    SET success_rate = 0;
                    SET fail_rate = 0;
                END IF;

                SELECT total_events,
                    total_final,
                    total_cancel,
                    decided_events,
                    success_rate,
                    fail_rate,
                    (total_events - decided_events) AS pending_events,
                    ROUND(((total_events - decided_events) / total_events) * 100, 2) AS pending_rate;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS get_event_success_rate");
    }
};
