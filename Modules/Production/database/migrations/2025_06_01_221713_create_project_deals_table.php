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
        $eventTypes = \App\Enums\Production\EventType::cases();
        $eventTypes = collect($eventTypes)->map(function ($item) {
            return $item->value;
        })->toArray();
        $classifications = ['s', 'a', 'b', 'c', 'd'];

        Schema::create('project_deals', function (Blueprint $table) use ($eventTypes) {
            $table->id();
            $table->string('name');
            $table->string('client_portal')->nullable();
            $table->date('project_date');
            $table->foreignId('customer_id')
                ->references('id')
                ->on('customers');
            $table->enum('event_type', $eventTypes);
            $table->string('venue');
            $table->string('collaboration')->nullable();
            $table->text('note')->nullable();
            $table->double('led_area')->default(0);
            $table->json('led_detail')->nullable()
                ->default(null);
            $table->bigInteger('country_id')->nullable();
            $table->bigInteger('state_id')->nullable();
            $table->bigInteger('city_id')->nullable();
            $table->foreignId('project_class_id')
                ->references('id')
                ->on('project_classes');
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->enum('equipment_type', ['lasika', 'others']);
            $table->boolean('is_high_season');
            $table->tinyInteger('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deals', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['project_class_id']);
        });
        Schema::dropIfExists('project_deals');
    }
};
