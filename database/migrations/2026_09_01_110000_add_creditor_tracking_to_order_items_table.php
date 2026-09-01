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
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('creditor_updated_by')->nullable()->after('creditor_code');
            $table->string('creditor_set_by_role', 30)->nullable()->after('creditor_updated_by');
            $table->timestamp('creditor_updated_at')->nullable()->after('creditor_set_by_role');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['creditor_updated_by', 'creditor_set_by_role', 'creditor_updated_at']);
        });
    }
};
