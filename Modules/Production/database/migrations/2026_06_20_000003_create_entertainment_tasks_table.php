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
        Schema::create('entertainment_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->nullable()
                ->references('id')
                ->on('projects');
            $table->string('type', 20)->nullable()->comment('song, demolight, title');
            $table->uuid('uid');
            $table->string('name', 255);
            $table->string('description', 255)->nullable();
            $table->timestamp('deadline')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->references('id')
                ->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_tasks');
    }
};
