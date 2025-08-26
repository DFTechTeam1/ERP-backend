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
        Schema::table('development_project_tasks', function (Blueprint $table) {
            $table->string('current_pic_id')->nullable()->after('status')
                ->comment('this will indicate who is the current worker, when task is revise, pic should be the same with this value. ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_project_tasks', function (Blueprint $table) {
            $table->dropColumn('current_pic_id');
        });
    }
};
