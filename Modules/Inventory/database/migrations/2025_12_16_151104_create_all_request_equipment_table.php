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
        Schema::create('request_equipment_masters', function (Blueprint $table) {
            $table->id();
            $table->char('uid', '36');
            $table->date('purchased_date');
            $table->string('batch_code')->comment('REQ-20260101');
            $table->string('note');
            $table->enum('status', [
                'pending',
                'on_process',
                'success',
                'cancel'
            ]);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('request_equipments', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 36);
            $table->foreignId('master_id')
                ->constrained('request_equipment_masters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('name');
            $table->integer('quantity');
            $table->bigInteger('price');
            $table->bigInteger('total_price');
            // inventory_id constrained and nullable
            $table->foreignId('inventory_id')
                ->nullable()
                ->constrained('inventories')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->bigInteger('supplier_id');
            $table->bigInteger('brand_id');
            $table->bigInteger('unit_id');
            $table->bigInteger('inventory_type_id');
            $table->enum('warehouse', [
                'office' ,
                'entertainment'
            ]);
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->enum('approval_status', [
                'fully_approved'  ,
                'partially_approved' ,
                'fully_rejected'
            ])->nullable();
            $table->enum('movement_status', [
                'on_process',
                'success'
            ])->nullable();
            $table->enum('order_type', [
                'online',
                'offline'
            ])->nullable();
            $table->integer('warranty_month')->nullable();
            $table->text('note')->nullable();
            $table->integer('remaining_item');
            $table->integer('resolved_item');
            $table->boolean('is_paid')->default(false);
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_save_logs', function (Blueprint $table){
            $table->id();
            $table->timestamp('created_at');
            $table->char('uid', 36);
            $table->foreignId('equipment_id')
                ->constrained('request_equipments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->integer('quantity');
        });

        Schema::create('equipment_refund_logs', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 36);
            $table->foreignId('equipment_id')
            ->constrained('request_equipments')
            ->onUpdate('cascade')
            ->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('total_refund');
            $table->text('note')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });

        Schema::create('equipment_return_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at');
            $table->char('uid', 36);
            $table->foreignId('equipment_id')
                ->constrained('request_equipments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->integer('quantity');
            $table->text('note')->nullable();
        });

        Schema::create('request_equipment_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')
                ->constrained('request_equipments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->char('equipment_uid', 36);
            $table->foreignId('master_id')
                ->constrained('request_equipment_masters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->json('previous');
            $table->json('incoming');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->enum('status', [
                'need_approval',
                'approved',
                'rejected'
            ]);
            $table->timestamps();
        });

        Schema::create('request_equipment_email_approvals', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('master_id')
                ->nullable()
                ->constrained('request_equipment_masters')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignId('equipment_id')
                ->nullable()
                ->constrained('request_equipments')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->text('token');
            $table->timestamp('expired_at');
            $table->boolean('is_used')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop request_equipment_email_approvals foreign
        Schema::table('request_equipment_email_approvals', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropForeign(['equipment_id']);
        });

        // Drop request_equipment_updates foreign
        Schema::table('request_equipment_updates', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
        });

        // Drop foreign in equipment_return_logs
        Schema::table('equipment_return_logs', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
        });

        // Drop foreign in equipment_refund_logs
        Schema::table('equipment_refund_logs', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
        });

        // Drop foreign in equipment_save_logs
        Schema::table('equipment_save_logs', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
        });

        // Drop foregin in request_equipments
        Schema::table('request_equipments', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropForeign(['inventory_id']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
        });

        Schema::dropIfExists('request_equipment_updates');
        Schema::dropIfExists('equipment_return_logs');
        Schema::dropIfExists('equipment_refund_logs');
        Schema::dropIfExists('equipment_save_logs');
        Schema::dropIfExists('request_equipments');
        Schema::dropIfExists('request_equipment_masters');
    }
};
