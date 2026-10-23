<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('return_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id')->nullable()->index();
            $table->string('factory_name')->nullable();
            $table->string('creditor_code')->index();
            $table->boolean('is_returnable')->default(true);
            $table->integer('months_before_ed')->nullable();
            $table->integer('months_after_ed')->default(0);
            $table->boolean('allow_on_ed_month')->default(false);
            $table->string('raw_rule')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('factory_id')->references('id')->on('factories')->onDelete('set null');
            $table->foreign('creditor_code')->references('code')->on('creditors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_policies');
    }
};
