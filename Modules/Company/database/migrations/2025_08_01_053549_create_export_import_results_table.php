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
        Schema::create('export_import_results', function (Blueprint $table) {
            $table->id();
            $table->string('area', 100);
            $table->string('description', 255);
            $table->longtext('message');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained(table: 'users', column: 'id')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('export_import_results', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::dropIfExists('export_import_results');
    }
};
