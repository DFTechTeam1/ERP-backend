<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Colors\Rgb\Channels\Blue;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('overtime_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')
                ->references('id')
                ->on('division_backups');
            $table->decimal('price_per_hour', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overtime_rate_settings', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });

        Schema::dropIfExists('overtime_rate_settings');
    }
};
