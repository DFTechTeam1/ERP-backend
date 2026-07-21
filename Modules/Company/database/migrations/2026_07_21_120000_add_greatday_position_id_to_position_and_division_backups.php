<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store Greatday's immutable numeric position id (positionId) so the position
     * sync can match on a stable key. posCode (greatday_code) can change in Greatday
     * and therefore cannot be used as the identity anchor.
     */
    public function up(): void
    {
        Schema::table('position_backups', function (Blueprint $table) {
            $table->unsignedBigInteger('greatday_position_id')->nullable()->index()->after('greatday_code');
        });

        Schema::table('division_backups', function (Blueprint $table) {
            $table->unsignedBigInteger('greatday_position_id')->nullable()->index()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('position_backups', function (Blueprint $table) {
            $table->dropColumn('greatday_position_id');
        });

        Schema::table('division_backups', function (Blueprint $table) {
            $table->dropColumn('greatday_position_id');
        });
    }
};
