<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_item_movements', function (Blueprint $table) {
            $table->uuid('consolidation_key')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_item_movements', function (Blueprint $table) {
            $table->dropColumn('consolidation_key');
        });
    }
};
