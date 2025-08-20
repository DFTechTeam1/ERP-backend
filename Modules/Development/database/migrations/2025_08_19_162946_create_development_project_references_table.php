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
        $types = array_map(fn($type) => $type->value, $types);
        Schema::create('development_project_references', function (Blueprint $table) use ($types) {
            $table->id();
            $table->char('uid', 36);
            $table->foreignId('development_project_id')
                ->constrained('development_projects')
                ->onDelete('cascade');
            $table->enum('type', $types);
            $table->string('media_path')->nullable();
            $table->string('link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('development_project_references', function (Blueprint $table) {
            $table->dropForeign(['development_project_id']);
        });

        Schema::dropIfExists('development_project_references');
    }
};
