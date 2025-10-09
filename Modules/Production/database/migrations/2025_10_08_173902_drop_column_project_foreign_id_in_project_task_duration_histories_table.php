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
        Schema::table('project_task_duration_histories', function (Blueprint $table) {
            // drop foreign key
            $foreignKeys = Schema::getForeignKeys('project_task_duration_histories');

            foreach ($foreignKeys as $foreignKey) {
                if ($foreignKey['name'] === 'project_task_duration_histories_project_id_foreign') {
                    $table->dropForeign('project_task_duration_histories_project_id_foreign');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
