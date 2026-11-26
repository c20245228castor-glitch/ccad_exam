<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('role')->default('user');
            $table->foreignId('consumer_id')
                ->nullable()
                ->unique()
                ->constrained('consumers', 'consumer_id')
                ->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->default('cash');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['consumer_id']);
            $table->dropUnique(['consumer_id']);
            $table->dropColumn('consumer_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role']);
        });
    }
};
