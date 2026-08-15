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
            if (!Schema::hasColumn('medicine_transactions', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('medicine_transactions', 'shift_logs_id')) {
                $table->foreignId('shift_logs_id')
                    ->nullable()
                    ->constrained('shift_logs')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('medicine_transactions', 'payment_method')) {
                $table->string('payment_method')->nullable();
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
        //
    }
};
