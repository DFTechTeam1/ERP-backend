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
        Schema::table('interactive_projects', function (Blueprint $table) {
            $table->string('classification')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $classifications = ['s', 'a', 'b', 'c', 'd'];

        Schema::table('interactive_projects', function (Blueprint $table) use ($classifications) {
            $table->enum('classification', $classifications)->nullable()->change();
        });
    }
};
