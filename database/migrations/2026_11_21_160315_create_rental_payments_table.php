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
        Schema::create('rental_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->enum('payment_type', ['initial', 'extension']);
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('added_days');
            $table->unsignedInteger('added_months')->nullable();
            $table->date('previous_end_date')->nullable();
            $table->date('new_end_date');
            $table->string('proof_image_path');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['rental_id', 'payment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
    }
};
