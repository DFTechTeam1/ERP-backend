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
        Schema::table('project_person_in_charges', function (Blueprint $table) {
            $table->boolean('is_lead')->default(false)->after('pic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_person_in_charges', function (Blueprint $table) {
            $table->dropColumn('is_lead');
        });
    }
};
