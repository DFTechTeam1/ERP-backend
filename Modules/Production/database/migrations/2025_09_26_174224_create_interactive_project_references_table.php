<?php

use App\Enums\Development\Project\ReferenceType;
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
        $types = ReferenceType::cases();
        $typeValues = array_map(fn ($type) => $type->value, $types);
        Schema::create('interactive_project_references', function (Blueprint $table) use ($typeValues) {
            $table->id();
            $table->char('uid', 36)->unique();
            $table->foreignId('project_id')->constrained('interactive_projects')->onDelete('cascade');
            $table->enum('type', $typeValues);
            $table->string('media_path')->nullable();
            $table->string('link')->nullable();
            $table->string('link_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('interactive_project_references', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
        Schema::dropIfExists('interactive_project_references');
    }
};
