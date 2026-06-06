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
        Schema::create('mcp_access_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable()->index();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->string('method', 10);
            $table->string('route_uri');
            $table->string('route_name')->nullable();
            $table->integer('status_code')->nullable();
            $table->boolean('is_success')->default(false)->index();
            $table->json('parameters')->nullable();
            $table->text('response_message')->nullable();
            $table->string('ip', 100)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('accessed_at')->index();
            $table->timestamps();

            $table->index(['route_uri', 'method']);
            $table->index(['user_id', 'accessed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_access_logs');
    }
};
