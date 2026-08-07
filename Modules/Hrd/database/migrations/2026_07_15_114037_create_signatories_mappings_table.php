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
        Schema::create('signatories_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('division_id')
                ->constrained('division_backups');
            $table->foreignId('main_signer_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->foreignId('delegate_signer_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatories_mappings', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['main_signer_id']);
            $table->dropForeign(['delegate_signer_id']);
            $table->dropForeign(['updated_by']);
        });
        
        Schema::dropIfExists('signatories_mappings');
    }
};
