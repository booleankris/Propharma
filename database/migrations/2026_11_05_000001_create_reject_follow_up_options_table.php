<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reject_follow_up_options')) {
            Schema::create('reject_follow_up_options', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id')->nullable()->index();
                $table->string('category', 50)->index(); // stock_status, follow_up, follow_up_update
                $table->string('label', 255);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reject_follow_up_options');
    }
};

