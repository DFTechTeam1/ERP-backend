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
        Schema::create('telegram_report_tasks', function (Blueprint $table) {
            $table->id();
            $table->integer('task_id')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->string('nas_link')->nullable();
            $table->string('file_id')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_report_tasks');
    }
};
