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
        Schema::create('rentals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('garage_id')->constrained('garages')->restrictOnDelete();
            $table->enum('vehicle_type', ['sepeda', 'sepeda_motor', 'mobil', 'mobil_listrik']);
            $table->string('license_plate', 30)->nullable();
            $table->string('vehicle_brand_type');
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable();
            $table->enum('rental_type', ['harian', 'bulanan']);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('initial_duration_days');
            $table->decimal('ev_charging_fee', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
            $table->index(['garage_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
