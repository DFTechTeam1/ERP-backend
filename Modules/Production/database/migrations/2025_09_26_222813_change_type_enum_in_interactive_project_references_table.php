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
        $type = ReferenceType::cases();
        $types = array_map(fn ($case) => $case->value, $type);

        Schema::table('interactive_project_references', function (Blueprint $table) use ($types) {
            $table->enum('type', $types)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $type = ReferenceType::cases();
        $types = array_map(fn ($case) => $case->value, $type);

        Schema::table('interactive_project_references', function (Blueprint $table) use ($types) {
            $table->enum('type', $types)->change();
        });
    }
};
