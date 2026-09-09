<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_consolidation')->default(false);
            $table->uuid('consolidation_key')->nullable()->unique();
            $table->string('note')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('original_quantity')->nullable();
            $table->string('original_total')->nullable();
        });
        Schema::create('order_item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('target_item_id')->unique()->constrained('order_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_movements');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['original_quantity', 'original_total']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['is_consolidation', 'consolidation_key', 'note']));
    }
};
