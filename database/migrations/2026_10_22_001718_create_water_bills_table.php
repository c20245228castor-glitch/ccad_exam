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
        Schema::create('water_bills', function (Blueprint $table) {
            
            $table->id('bill_id');
            $table->foreignId('meter_id')
                ->constrained('water_meters', 'meter_id')
                ->restrictOnDelete();
            $table->foreignId('rate_id')
                ->constrained('water_rates', 'rate_id')
                ->restrictOnDelete();
            $table->decimal('previous_reading', 10, 2);
            $table->decimal('current_reading', 10, 2);
            $table->decimal('consumption_cu_m', 10, 2)->default(0);
            $table->date('billing_date');
            $table->date('due_date');
            $table->decimal('total_water_bill', 10, 2)->default(0);
            $table->decimal('penalty_fee', 10, 2)->default(0);
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_bills');
    }
};
