<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reported_medicines', function (Blueprint $table) {
            $table->foreignId('pharmacy_id')->nullable()->after('id')->constrained('pharmacies')->cascadeOnDelete();
        });

        // Replicate existing reported medicines for active retail pharmacies (1, 2, 3, 4, 5)
        $existing = DB::table('reported_medicines')->whereNull('pharmacy_id')->get();
        if ($existing->isNotEmpty()) {
            $pharmacyIds = [1, 2, 3, 4, 5];
            
            // Assign existing to pharmacy 1
            DB::table('reported_medicines')->whereNull('pharmacy_id')->update(['pharmacy_id' => 1]);

            // Copy to pharmacies 2, 3, 4, 5
            foreach ([2, 3, 4, 5] as $pid) {
                foreach ($existing as $item) {
                    DB::table('reported_medicines')->insert([
                        'pharmacy_id' => $pid,
                        'medicine_id' => $item->medicine_id,
                        'user_id'     => $item->user_id,
                        'notes'       => $item->notes,
                        'created_at'  => $item->created_at ?? now(),
                        'updated_at'  => $item->updated_at ?? now(),
                    ]);
                }
            }
        }

        // Add compound unique index
        Schema::table('reported_medicines', function (Blueprint $table) {
            $table->unique(['pharmacy_id', 'medicine_id'], 'reported_medicines_pharmacy_medicine_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reported_medicines', function (Blueprint $table) {
            $table->dropUnique('reported_medicines_pharmacy_medicine_unique');
            $table->dropForeign(['pharmacy_id']);
            $table->dropColumn('pharmacy_id');
        });
    }
};
