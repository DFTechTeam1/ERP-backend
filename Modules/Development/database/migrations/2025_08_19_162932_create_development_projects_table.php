<?php

use App\Enums\Development\Project\ProjectStatus;
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
        $projectStatus = ProjectStatus::cases();
        $projectStatus = array_map(fn($status) => $status->value, $projectStatus);
        Schema::create('development_projects', function (Blueprint $table) use($projectStatus) {
            $table->id();
            $table->char('uid', 36);
            $table->string('name');
            $table->enum('status', $projectStatus);
            $table->text('description')->nullable();
            $table->date('project_date')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_projects');
    }
};
