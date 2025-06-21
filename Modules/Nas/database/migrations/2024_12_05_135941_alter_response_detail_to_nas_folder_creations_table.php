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
            $table->json('server_response')->nullable();
            $table->string('failed_reason')->nullable();
            $table->json('current_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nas_folder_creations', function (Blueprint $table) {
            $table->dropColumn('server_response');
            $table->dropColumn('failed_reason');
            $table->dropColumn('current_path');
        });
    }
};
