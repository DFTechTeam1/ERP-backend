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
        Schema::create('nas_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('cmd_type', ['create', 'write', 'read', 'delete']);
            $table->text('description');
            $table->string('filesize')->comment('in kilobyte');
            $table->string('ip');
            $table->boolean('is_dir');
            $table->string('log_type', 20);
            $table->string('original_log_type');
            $table->timestamp('time');
            $table->string('username');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nas_logs');
    }
};
