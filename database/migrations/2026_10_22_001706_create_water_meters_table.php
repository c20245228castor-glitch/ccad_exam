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
        Schema::create('water_meters', function (Blueprint $table) {
           
            $table->id('meter_id');
            $table->foreignId('consumer_id')
                ->constrained('consumers', 'consumer_id')
                ->cascadeOnDelete();
            $table->string('meter_serial')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_meters');
    }
};
