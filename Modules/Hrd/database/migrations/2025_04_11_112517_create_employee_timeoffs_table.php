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
        Schema::create('employee_timeoffs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('time_off_id')
                ->comment('This data came from Talenta server');
            $table->bigInteger('talenta_user_id')
                ->nullable();
            $table->string('policy_name')
                ->nullable()
                ->comment('This data came from Talenta server');
            $table->string('request_type', 100)
                ->nullable()
                ->comment('This data came from Talenta server');
            $table->string('file_url')
                ->nullable()
                ->comment('This data came from Talenta server');
            $table->date('start_date')
                ->nullable()
                ->comment('This data came from Talenta server');
            $table->date('end_date')
                ->nullable()
                ->comment('This data came from Talenta server');
            $table->string('status', 100)
                ->nullable()
                ->comment('This data came from Talenta server');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_timeoffs');
    }
};
