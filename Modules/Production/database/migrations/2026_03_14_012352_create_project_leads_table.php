<?php

use App\Enums\Production\EventType;
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
        $eventTypes = EventType::cases();
        Schema::create('project_leads', function (Blueprint $table) use ($eventTypes) {
            $table->id();
            $table->string('name');
            $table->date('project_date');
            $table->enum('event_type', array_map(fn($type) => $type->value, $eventTypes))
                ->nullable();
            $table->string('venue')->nullable();
            $table->foreignId('city_id')
                ->nullable()
                ->constrained('cities')
                ->nullOnDelete();
            $table->string('pic_id')->nullable()
                ->comment('Can be multiple separated by commas');
            $table->string('collaboration')->nullable();
            $table->text('note')->nullable();
            $table->string('total_led', 100)->nullable();
            $table->foreignId('project_class_id')
                ->nullable()
                ->constrained('project_classes')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->constrained('employees');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('employees');
            $table->boolean('is_final')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_leads', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['project_class_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('project_leads');
    }
};
