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
        if (checkForeignKey(tableName: 'project_equipment', columnName: 'project_id')) {
            Schema::table('project_equipment', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }

        Schema::dropIfExists('project_equipment'); // delete old table

        // create new table
        Schema::create('project_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->references(column: 'id')
                ->on(table: "projects")
                ->cascadeOnDelete();
            $table->integer('total_equipment')->default(0);
            $table->tinyInteger('status');
            $table->string('type', 20)->comment('If there have bundle and standalone inventory, then will be categorize as combine');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_equipments', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
        Schema::dropIfExists('project_equipments');

        Schema::create('project_equipment', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->integer('inventory_id');
            $table->integer('qty');
            $table->tinyInteger('status')
                ->comment('1 for ready, 2 for requested, 3 for decline');
            $table->date('project_date');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }
};
