<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('medicine_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_transactions', 'has_doctor_shipping_fee')) {
                $table->boolean('has_doctor_shipping_fee')->default(false)->after('transfer_bank_name');
            }
            if (!Schema::hasColumn('medicine_transactions', 'doctor_shipping_fee')) {
                $table->decimal('doctor_shipping_fee', 15, 2)->default(0)->after('has_doctor_shipping_fee');
            }
            if (!Schema::hasColumn('medicine_transactions', 'doctor_shipping_payment_method')) {
                $table->string('doctor_shipping_payment_method')->nullable()->after('doctor_shipping_fee');
            }
            if (!Schema::hasColumn('medicine_transactions', 'doctor_shipping_bank_name')) {
                $table->string('doctor_shipping_bank_name')->nullable()->after('doctor_shipping_payment_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medicine_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('medicine_transactions', 'doctor_shipping_bank_name')) {
                $table->dropColumn('doctor_shipping_bank_name');
            }
            if (Schema::hasColumn('medicine_transactions', 'doctor_shipping_payment_method')) {
                $table->dropColumn('doctor_shipping_payment_method');
            }
            if (Schema::hasColumn('medicine_transactions', 'doctor_shipping_fee')) {
                $table->dropColumn('doctor_shipping_fee');
            }
            if (Schema::hasColumn('medicine_transactions', 'has_doctor_shipping_fee')) {
                $table->dropColumn('has_doctor_shipping_fee');
            }
        });
    }
};
