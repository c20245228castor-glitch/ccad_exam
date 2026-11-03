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
    Schema::create('payments', function (Blueprint $table) {
        $table->id('payment_id');

        $table->foreignId('bill_id')
            ->constrained('water_bills', 'bill_id')
            ->restrictOnDelete();

        $table->decimal('amount_paid', 10, 2);
        $table->dateTime('payment_date');
        $table->string('payment_reference')->unique();
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
