<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('finance_accounts')) {
            Schema::create('finance_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->string('category')->default('Kas & Bank'); // Kas & Bank, Hutang Usaha, Piutang Usaha, dll.
                $table->string('account_number')->nullable();
                $table->decimal('balance', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed default Kas & Bank accounts ala Kledo
            DB::table('finance_accounts')->insert([
                [
                    'code' => '1-10001',
                    'name' => 'Kas Apotek / Drawer',
                    'name_en' => 'Cash Drawer',
                    'category' => 'Kas & Bank',
                    'account_number' => null,
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => '1-10002',
                    'name' => 'Rekening BCA',
                    'name_en' => 'BCA Bank Account',
                    'category' => 'Kas & Bank',
                    'account_number' => '8295019281',
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => '1-10003',
                    'name' => 'Rekening Mandiri',
                    'name_en' => 'Mandiri Bank Account',
                    'category' => 'Kas & Bank',
                    'account_number' => '1420019283741',
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => '1-10004',
                    'name' => 'Rekening BNI',
                    'name_en' => 'BNI Bank Account',
                    'category' => 'Kas & Bank',
                    'account_number' => '0382918293',
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => '1-10005',
                    'name' => 'Rekening BTN',
                    'name_en' => 'BTN Bank Account',
                    'category' => 'Kas & Bank',
                    'account_number' => '001201500029180',
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (!Schema::hasTable('finance_payments')) {
            Schema::create('finance_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('receiving_id')->constrained('receiving')->cascadeOnDelete();
                $table->foreignId('receiving_detail_id')->nullable()->constrained('receiving_details')->nullOnDelete();
                $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
                $table->string('payment_type')->default('KREDIT'); // KREDIT / CASH
                $table->date('payment_date');
                $table->decimal('amount', 15, 2);
                $table->string('reference_number')->nullable(); // No Ref Bukti Transfer
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('finance_payments');
        Schema::dropIfExists('finance_accounts');
    }
};
