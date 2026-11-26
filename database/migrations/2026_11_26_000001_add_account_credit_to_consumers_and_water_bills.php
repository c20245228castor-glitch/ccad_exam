<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->decimal('account_credit', 10, 2)->default(0);
        });

        Schema::table('water_bills', function (Blueprint $table) {
            $table->decimal('credit_applied', 10, 2)->default(0);
        });

        $existingCredits = DB::table('payments')
            ->join('water_bills', 'payments.bill_id', '=', 'water_bills.bill_id')
            ->join('water_meters', 'water_bills.meter_id', '=', 'water_meters.meter_id')
            ->select('water_meters.consumer_id')
            ->selectRaw(
                'SUM(CASE WHEN payments.amount_paid > water_bills.total_water_bill THEN payments.amount_paid - water_bills.total_water_bill ELSE 0 END) AS account_credit'
            )
            ->groupBy('water_meters.consumer_id')
            ->get();

        foreach ($existingCredits as $existingCredit) {
            DB::table('consumers')
                ->where('consumer_id', $existingCredit->consumer_id)
                ->update(['account_credit' => $existingCredit->account_credit]);
        }
    }

    public function down(): void
    {
        Schema::table('water_bills', function (Blueprint $table) {
            $table->dropColumn('credit_applied');
        });

        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn('account_credit');
        });
    }
};
