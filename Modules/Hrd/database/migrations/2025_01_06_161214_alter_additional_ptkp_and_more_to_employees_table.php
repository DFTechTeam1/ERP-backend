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
            $table->string('ptkp_status', 5)->nullable();
            $table->decimal('basic_salary', 20, 2)->default(0);
            $table->enum('salary_type', ['month', 'daily']);

            // delete column
            $table->dropColumn('dependant');
            $table->dropColumn('placement');
            $table->dropColumn('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('ptkp_status');
            $table->dropColumn('basic_salary');
            $table->dropColumn('salary_type');

            // delete column
            $table->string('placement')->nullable();
            $table->string('dependant', 10)->nullable()->comment('tanggungan');
            $table->string('company_name')->nullable();
        });
    }
};
