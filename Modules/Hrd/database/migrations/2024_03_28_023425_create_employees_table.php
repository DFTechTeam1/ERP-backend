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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('email');
            $table->string('phone', 15);
            $table->string('id_number', 16);
            $table->enum('religion', ['katholik', 'islam', 'kristen', 'budha', 'hindu', 'khonghucu']);
            $table->enum('martial_status', ['married', 'single']);
            $table->string('address');
            $table->integer('city_id')->nullable();
            $table->integer('province_id')->nullable();
            $table->integer('country_id')->nullable();
            $table->string('postal_code', 6);
            $table->string('current_address')->nullable();
            $table->string('blood_type', 2)->nullable();
            $table->date('date_of_birth');
            $table->string('place_of_birth');
            $table->string('dependant', 10)->nullable()->comment('tanggungan');
            $table->enum('gender', ['female', 'male']);
            $table->json('bank_detail')
                ->comment('Format: [{"bank_name": "", "account_number": "", "account_holder_name": "", "is_active": ""}]')
                ->nullable();
            $table->json('relation_contact')
                ->comment('Format: {"name": "", "phone": "", "relationship": ""}')
                ->nullable();
            $table->enum('education', ['smp', 'sma', 'smk', 'diploma', 's1', 's2', 's3']);
            $table->string('education_name')->nullable();
            $table->string('education_major')->nullable();
            $table->year('education_year')->nullable();
            $table->foreignId('position_id')
                ->references('id')
                ->on('positions');
            $table->integer('boss_id')->nullable();
            $table->enum('level_staff', ['manager', 'staff', 'lead', 'junior_staff']);
            $table->tinyInteger('status')
                ->comment('1 for permanenet, 2 for contract, 3 for part time, 4 for freelance, 5 for internship, 6 for inactive, 7 for waiting HR checking (usually comes from self-filling after the contract)');
            $table->string('placement')->nullable();
            $table->date('join_date');
            $table->date('start_review_probation_date')->nullable();
            $table->tinyInteger('probation_status')->nullable();
            $table->date('end_probation_date')->nullable();
            $table->string('company_name')->nullable();
            $table->tinyInteger('bpjs_status')->nullable();
            $table->string('bpjs_ketenagakerjaan_number', 50)->nullable();
            $table->string('bpjs_kesehatan_number', 50)->nullable();
            $table->string('npwp_number', 50)->nullable();
            $table->string('bpjs_photo')->nullable();
            $table->string('npwp_photo')->nullable();
            $table->string('id_number_photo')->nullable();
            $table->string('kk_photo')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
        });

        Schema::dropIfExists('employees');
    }
};
