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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('tax_configuration')->nullable();
            $table->string('employee_tax_status')->nullable();
            $table->string('salary_configuration')->nullable();
            $table->string('jht_configuration')->nullable();
            $table->string('jp_configuration')->nullable();
            $table->string('overtime_status')->nullable();
            $table->string('bpjs_kesehatan_config')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('tax_configuration');
            $table->dropColumn('employee_tax_status');
            $table->dropColumn('salary_configuration');
            $table->dropColumn('jht_configuration');
            $table->dropColumn('jp_configuration');
            $table->dropColumn('overtime_status');
            $table->dropColumn('bpjs_kesehatan_config');
        });
    }
};
