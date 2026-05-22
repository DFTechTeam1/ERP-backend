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
        Schema::create('greatday_resign_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('resign_type')->nullable();
            $table->foreign('resign_type')
                ->nullable()
                ->references('code')
                ->on('greatday_resign_types')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('greatday_resign_reasons', function (Blueprint $table) {
            $table->dropForeign(['resign_type']);
        });

        Schema::dropIfExists('greatday_resign_reasons');
    }
};
