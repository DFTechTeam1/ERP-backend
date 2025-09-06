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
        Schema::table('export_import_results', function (Blueprint $table) {
            $table->enum('type', ['old_area', 'new_area'])->default('old_area')->comment('Old area used to render notification for old interface, new_area used for new interface');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('export_import_results', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
