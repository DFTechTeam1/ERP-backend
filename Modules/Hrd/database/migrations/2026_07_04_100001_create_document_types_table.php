<?php

use App\Enums\Hrd\Signature\DocumentType\Type;
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
        $types = array_map(fn($case) => $case->value, Type::cases());

        Schema::create('document_types', function (Blueprint $table) use ($types) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 100)->unique();
            $table->tinyInteger('retention')->default(1)->comment('in years');
            $table->tinyInteger('default_number_of_signers')->default(1);
            $table->tinyInteger('status')->default(1);
            $table->enum('category', $types)->comment('hr, legal, finance, compliance');
            $table->timestamps();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
