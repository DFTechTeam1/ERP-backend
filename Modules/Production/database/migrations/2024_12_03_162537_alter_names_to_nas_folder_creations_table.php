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
        Schema::table('nas_folder_creations', function (Blueprint $table) {
            $table->json("last_folder_name");
            $table->string("current_folder_name")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nas_folder_creations', function (Blueprint $table) {
            $table->dropColumn("last_folder_name");
            $table->dropColumn("current_folder_name");
        });
    }
};
