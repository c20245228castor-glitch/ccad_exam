
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumers', function (Blueprint $table) {
            $table->id('consumer_id');
            $table->string('account_number')->unique();
            $table->string('consumer_name');
            $table->string('consumer_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumers');
    }
};
