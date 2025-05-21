<?php

use App\Enums\Inventory\EventEquipmentType;
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
        $cases = EventEquipmentType::cases();
        $types = [];
        foreach ($cases as $case) {
            $types[] = $case->value;
        }

        Schema::create('event_equipments', function (Blueprint $table) use ($types) {
            $table->id();
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->enum('type', $types);
            $table->foreignId('requested_by')
                ->references('id')
                ->on('employees');
            $table->tinyInteger('status')
                ->comment('1 for requested, 2 for processed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_equipments');
    }
};
