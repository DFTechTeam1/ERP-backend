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

        Schema::create('interactive_projects', function (Blueprint $table) use ($eventTypes, $classifications) {
            $table->id();
            $table->char('uid', 36);
            $table->string('name');
            $table->string('client_portal');
            $table->foreignId('parent_project')->constrained('projects')->onDelete('cascade');
            $table->date('project_date');
            $table->enum('event_type', $eventTypes)->nullable();
            $table->string('venue');
            $table->integer('marketing_id')->nullable();
            $table->string('collaboration')->nullable();
            $table->tinyInteger('status')
                ->comment('1 for active-ongoing, 2 is draft, 3 for done, 4 for waiting approval, 5 completed, 6 for cancel');
            $table->enum('classification', $classifications)->nullable();
            $table->text('note')->nullable();
            $table->decimal('led_area', 8, 2)->default('0');
            $table->json('led_detail')->nullable();
            $table->foreignId('project_class_id')->constrained('project_classes')->onDelete('cascade');
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
        // drop foreign
        Schema::table('interactive_projects', function (Blueprint $table) {
            $table->dropForeign(['parent_project']);
            $table->dropForeign(['project_class_id']);
        });
        Schema::dropIfExists('interactive_projects');
    }
};
