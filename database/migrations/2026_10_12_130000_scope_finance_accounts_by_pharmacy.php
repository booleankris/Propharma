<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('finance_accounts', 'pharmacy_id')) {
            Schema::table('finance_accounts', function (Blueprint $table) {
                $table->foreignId('pharmacy_id')->nullable()->after('id')->constrained('pharmacies')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('finance_accounts', 'account_type')) {
            Schema::table('finance_accounts', function (Blueprint $table) {
                $table->string('account_type', 30)->default('bank')->after('category');
            });
        }

        // Preserve the old shared accounts as legacy records for audit history.
        DB::table('finance_accounts')->where('category', 'Kas & Bank')->where('code', '1-10001')->update(['account_type' => 'kas_apotek']);

        if (Schema::hasIndex('finance_accounts', 'finance_accounts_code_unique')) {
            Schema::table('finance_accounts', fn (Blueprint $table) => $table->dropUnique('finance_accounts_code_unique'));
        }
        if (!Schema::hasIndex('finance_accounts', 'finance_accounts_pharmacy_code_unique')) {
            Schema::table('finance_accounts', fn (Blueprint $table) => $table->unique(['pharmacy_id', 'code'], 'finance_accounts_pharmacy_code_unique'));
        }

        $legacyAccounts = DB::table('finance_accounts')->where('category', 'Kas & Bank')->whereNull('pharmacy_id')->get();
        $pharmacies = DB::table('pharmacies')->orderBy('id')->get(['id']);
        $accountMap = [];

        foreach ($pharmacies as $pharmacy) {
            $hasCashAccount = false;
            foreach ($legacyAccounts as $legacy) {
                $newId = DB::table('finance_accounts')->insertGetId([
                    'pharmacy_id' => $pharmacy->id,
                    'code' => $legacy->code,
                    'name' => $legacy->name,
                    'name_en' => $legacy->name_en,
                    'category' => 'Kas & Bank',
                    'account_type' => $legacy->code === '1-10001' ? 'kas_apotek' : 'bank',
                    'account_number' => $legacy->account_number,
                    // Opening balances are intentionally entered for each branch after cutover.
                    'balance' => 0,
                    'is_active' => $legacy->code === '1-10001' ? true : (bool) $legacy->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $accountMap[$pharmacy->id][$legacy->id] = $newId;
                if ($legacy->code === '1-10001') $hasCashAccount = true;
            }
            if (!$hasCashAccount) {
                DB::table('finance_accounts')->insert([
                    'pharmacy_id' => $pharmacy->id,
                    'code' => '1-10001',
                    'name' => 'Kas Apotek / Drawer',
                    'name_en' => 'Cash Drawer',
                    'category' => 'Kas & Bank',
                    'account_type' => 'kas_apotek',
                    'account_number' => null,
                    'balance' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('finance_accounts')->where('category', 'Kas & Bank')->whereNull('pharmacy_id')->update(['is_active' => false]);

        // Re-link finance history to the account copy for the pharmacy on its source transaction.
        $legacyIds = $legacyAccounts->pluck('id')->all();
        DB::table('finance_payments')->where(function ($query) use ($legacyIds) {
            $query->whereIn('account_id', $legacyIds)->orWhereIn('transfer_account_id', $legacyIds);
        })->orderBy('id')->chunkById(500, function ($payments) use ($accountMap) {
            foreach ($payments as $payment) {
                $pharmacyId = $payment->pharmacy_id;
                if (!$pharmacyId && $payment->medicine_transaction_id) {
                    $pharmacyId = DB::table('medicine_transactions')->where('id', $payment->medicine_transaction_id)->value('pharmacy_id');
                }
                if (!$pharmacyId && $payment->receiving_id) {
                    $pharmacyId = DB::table('receiving')->where('id', $payment->receiving_id)->value('pharmacy_id');
                }
                if (!$pharmacyId && $payment->receiving_detail_id) {
                    $receivingId = DB::table('receiving_details')->where('id', $payment->receiving_detail_id)->value('receiving_id');
                    if ($receivingId) $pharmacyId = DB::table('receiving')->where('id', $receivingId)->value('pharmacy_id');
                }
                $map = $accountMap[$pharmacyId] ?? [];
                $updates = [];
                if ($pharmacyId && !$payment->pharmacy_id) $updates['pharmacy_id'] = $pharmacyId;
                if (isset($map[$payment->account_id])) $updates['account_id'] = $map[$payment->account_id];
                if (!empty($payment->transfer_account_id) && isset($map[$payment->transfer_account_id])) {
                    $updates['transfer_account_id'] = $map[$payment->transfer_account_id];
                }
                if ($updates) DB::table('finance_payments')->where('id', $payment->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('finance_accounts', 'pharmacy_id')) return;

        $legacyByCode = DB::table('finance_accounts')->whereNull('pharmacy_id')->where('category', 'Kas & Bank')->pluck('id', 'code');
        $branchAccounts = DB::table('finance_accounts')->whereNotNull('pharmacy_id')->where('category', 'Kas & Bank')->get(['id', 'code']);
        foreach ($branchAccounts as $account) {
            if (isset($legacyByCode[$account->code])) {
                DB::table('finance_payments')->where('account_id', $account->id)->update(['account_id' => $legacyByCode[$account->code]]);
                DB::table('finance_payments')->where('transfer_account_id', $account->id)->update(['transfer_account_id' => $legacyByCode[$account->code]]);
            }
        }
        DB::table('finance_accounts')->whereNotNull('pharmacy_id')->where('category', 'Kas & Bank')->delete();
        DB::table('finance_accounts')->whereNull('pharmacy_id')->where('category', 'Kas & Bank')->update(['is_active' => true]);

        if (Schema::hasIndex('finance_accounts', 'finance_accounts_pharmacy_code_unique')) {
            Schema::table('finance_accounts', fn (Blueprint $table) => $table->dropUnique('finance_accounts_pharmacy_code_unique'));
        }
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->unique('code', 'finance_accounts_code_unique');
            $table->dropConstrainedForeignId('pharmacy_id');
            $table->dropColumn('account_type');
        });
    }
};
