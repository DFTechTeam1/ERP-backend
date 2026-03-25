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
        Schema::create('project_lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_lead_id')->constrained('project_leads')->onDelete('cascade');
            $table->date('follow_up_date');
            $table->string('customer_phone', 20);
            $table->text('message');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('employees');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_lead_follow_ups', function (Blueprint $table) {
            $table->dropForeign(['project_lead_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::dropIfExists('project_lead_follow_ups');
    }
};
