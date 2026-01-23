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
        Schema::create('division_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('division_backups')->onDelete('cascade');
            $table->foreignId('allowance_id')->constrained('allowances')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        Schema::table('division_allowances', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['allowance_id']);
        });

        Schema::dropIfExists('division_allowances');
    }
};
