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
        Schema::create('project_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_deal_id')
                ->references('id')
                ->on('project_deals');
            $table->decimal('main_ballroom', 24, 0)->default(0);
            $table->decimal('prefunction', 24, 0)->default(0);
            $table->decimal('high_season_fee', 24, 0)->default(0);
            $table->decimal('equipment_fee', 24, 0)->default(0);
            $table->decimal('sub_total', 24, 0)->default(0);
            $table->decimal('maximum_discount', 24, 0)->default(0);
            $table->decimal('total', 24, 0)->default(0);
            $table->decimal('maximum_markup_price', 24, 0)->default(0);
            $table->decimal('fix_price', 24, 0)->default(0);
            $table->string('quotation_id');
            $table->boolean('is_final')->default(false);
            $table->string('event_location_guide')->nullable();
            $table->enum('equipment_type', ['lasika', 'others']);
            $table->boolean('is_high_season')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_quotations');
    }
};
