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
        Schema::create('project_transportation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportation_id')
                ->constrained('project_transportations')
                ->onDelete('cascade');
            $table->foreignId('project_id')
                ->constrained('projects')
                ->onDelete('cascade');
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->string('ticket_image')->nullable();
            $table->enum('type', ['departure', 'return']);
            $table->timestamp('departure_datetime')->nullable();
            $table->timestamp('return_datetime')->nullable();
            $table->enum('transportation_type', ['air', 'train', 'car', 'other']);
            $table->decimal('amount', 24, 2)->default(0);
            $table->foreignId('issued_by')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('project_transportation_details', function (Blueprint $table) {
            $table->dropForeign(['transportation_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['issued_by']);
        });

        Schema::dropIfExists('project_transportation_details');
    }
};
