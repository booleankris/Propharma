<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medicine_price_history', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_price_history', 'old_price')) {
                $table->string('old_price')->nullable()->after('medicine_id');
            }
        });

        // Backfill old_price from chronologically preceding records for the same medicine
        $records = DB::table('medicine_price_history')->orderBy('id', 'asc')->get();
        $lastPricePerMedicine = [];
        foreach ($records as $record) {
            if (isset($lastPricePerMedicine[$record->medicine_id])) {
                DB::table('medicine_price_history')
                    ->where('id', $record->id)
                    ->update(['old_price' => $lastPricePerMedicine[$record->medicine_id]]);
            }
            $lastPricePerMedicine[$record->medicine_id] = $record->new_price;
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medicine_price_history', function (Blueprint $table) {
            if (Schema::hasColumn('medicine_price_history', 'old_price')) {
                $table->dropColumn('old_price');
            }
        });
    }
};
