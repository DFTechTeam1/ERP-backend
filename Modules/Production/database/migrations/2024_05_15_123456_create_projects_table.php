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
        $eventTypes = ['wedding', 'engagement', 'event', 'birthday', 'concert', 'corporate', 'exhibition'];
        $classifications = ['s', 'a', 'b', 'c', 'd'];

        Schema::create('projects', function (Blueprint $table) use ($eventTypes, $classifications) {
            $table->id();
            $table->string('name');
            $table->string('client_portal');
            $table->date('project_date');
            $table->enum('event_type', $eventTypes)->nullable();
            $table->string('venue');
            $table->integer('marketing_id')->nullable();
            $table->string('collaboration')->nullable();
            $table->string('note')->nullable();
            $table->tinyInteger('status')
                ->comment('1 for active-ongoing, 2 is draft, 3 for done, 4 for waiting approval, 5 completed, 6 for cancel');
            $table->enum('classification', $classifications)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
