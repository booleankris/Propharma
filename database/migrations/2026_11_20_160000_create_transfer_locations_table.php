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
        if (!Schema::hasTable('transfer_locations')) {
            Schema::create('transfer_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('pharmacy_id')->nullable()->default(1);
                $table->integer('status')->default(1);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        $now = now();
        foreach (['Pelayanan Lt.1', 'Pelayanan Lt.2', 'Online'] as $defaultName) {
            $exists = DB::table('transfer_locations')
                ->where('pharmacy_id', 1)
                ->whereRaw('LOWER(name) = ?', [strtolower($defaultName)])
                ->exists();

            if (!$exists) {
                DB::table('transfer_locations')->insert([
                    'name' => $defaultName,
                    'pharmacy_id' => 1,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_transfer_items', 'transfer_location_id')) {
                $table->foreignId('transfer_location_id')
                    ->nullable()
                    ->after('etalases_id')
                    ->constrained('transfer_locations')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('medicine_transfer_items', 'location_updated_by')) {
                $table->unsignedBigInteger('location_updated_by')
                    ->nullable()
                    ->after('etalase_updated_at');
            }

            if (!Schema::hasColumn('medicine_transfer_items', 'location_updated_at')) {
                $table->timestamp('location_updated_at')
                    ->nullable()
                    ->after('location_updated_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicine_transfer_items', function (Blueprint $table) {
            if (Schema::hasColumn('medicine_transfer_items', 'transfer_location_id')) {
                $table->dropConstrainedForeignId('transfer_location_id');
            }
            if (Schema::hasColumn('medicine_transfer_items', 'location_updated_at')) {
                $table->dropColumn('location_updated_at');
            }
            if (Schema::hasColumn('medicine_transfer_items', 'location_updated_by')) {
                $table->dropColumn('location_updated_by');
            }
        });

        Schema::dropIfExists('transfer_locations');
    }
};
