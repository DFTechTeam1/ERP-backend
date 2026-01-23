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
        Schema::table('custom_inventories', function (Blueprint $table) {
            $table->integer('status')->default(1)->comment('1 for available, 2 for assigned');
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->dropColumn('default_request_item');
            $table->dropColumn('location');
            $table->dropColumn('build_series');
            $table->dropColumn('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_inventories', function (Blueprint $table) {
            $table->string('barcode')->nullable();
            $table->string('build_series')->nullable();
            $table->string('location')->nullable();
            $table->tinyInteger('default_request_item')->nullable();

            $table->dropColumn('status');
            if (Schema::hasIndex('custom_inventories', 'custom_inventories_created_by_foreign')) {
                $table->dropForeign(['created_by']);
            }
            if (Schema::hasIndex('custom_inventories', 'custom_inventories_updated_by_foreign')) {
                $table->dropForeign(['updated_by']);
            }
            $table->dropColumn('created_by');
            $table->dropColumn('updated_by');
        });
    }
};
