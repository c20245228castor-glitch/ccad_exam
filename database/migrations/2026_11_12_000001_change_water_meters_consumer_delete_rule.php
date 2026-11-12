<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_meters', function (Blueprint $table) {
            $table->dropForeign(['consumer_id']);

            $table->foreign('consumer_id')
                ->references('consumer_id')
                ->on('consumers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('water_meters', function (Blueprint $table) {
            $table->dropForeign(['consumer_id']);

            $table->foreign('consumer_id')
                ->references('consumer_id')
                ->on('consumers')
                ->cascadeOnDelete();
        });
    }
};
