<?php

use App\Enums\Interactive\InteractiveTaskStatus;
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
        $status = InteractiveTaskStatus::cases();
        $status = array_map(fn ($case) => strtolower($case->value), $status);
        Schema::create('intr_project_tasks', function (Blueprint $table) use ($status) {
            $table->id();
            $table->foreignId('intr_project_id')->constrained('interactive_projects')->onDelete('cascade');
            $table->foreignId('intr_project_board_id')->constrained('interactive_project_boards')->onDelete('cascade');
            $table->char('uid', 36)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->enum('status', $status)->default(InteractiveTaskStatus::Draft->value);
            $table->string('current_pic_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign key constraints first
        Schema::table('intr_project_tasks', function (Blueprint $table) {
            $table->dropForeign(['intr_project_id']);
            $table->dropForeign(['intr_project_board_id']);
        });

        Schema::dropIfExists('intr_project_tasks');
    }
};
