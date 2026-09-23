<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineTransactions extends Model
{
    use HasFactory;
    protected $table = 'medicine_transactions';
    protected $fillable = [
        'pharmacy_id',
        'debtor_id',
        'doctor_id',
        'patient_id',
        'transaction_type',
        'transaction_code',
        'paid',
        'changes',
        'subtotal',
        'discount',
        'status',
        'user_id',
        'payment_method',
        'shift_logs_id',
        'transfer_bank_name',

    ];
    public function transactions()
    {
        return $this->hasMany(MedicineCart::class, 'transaction_id');
    }
    public function patients()
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }
    public function doctors()
    {
        return $this->belongsTo(Doctors::class, 'doctor_id');
    }
    public function debtors()
    {
        return $this->belongsTo(Debtors::class, 'debtor_id');
    }
    public function shift_logs()
    {
        return $this->belongsTo(ShiftLogs::class, 'shift_logs_id');
    }
    public function medicine()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }
    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'medicine_transaction_id');
    }

    public function getChannelInfoAttribute()
    {
        $user = $this->relationLoaded('user') ? $this->user : $this->user()->with('roles')->first();
        return self::resolveChannelInfo(null, $user);
    }

    public static function resolveChannelInfo($cartUser = null, $txUser = null, $extraUsers = [])
    {
        $getUserRoles = function ($user) {
            if (!$user) return [];
            if ($user->relationLoaded('roles')) {
                return $user->roles->pluck('name')->map(fn($r) => strtolower(trim($r)))->toArray();
            }
            return $user->roles()->pluck('name')->map(fn($r) => strtolower(trim($r)))->toArray();
        };

        $allCandidates = collect([$cartUser, $txUser]);
        if (!empty($extraUsers)) {
            $allCandidates = $allCandidates->concat($extraUsers);
        }
        $allCandidates = $allCandidates->filter()->unique('id');

        $hasRole = function ($needle, $roles) {
            return in_array(strtolower($needle), $roles);
        };

        // 1. Digital (Aplikasi Mobile)
        foreach ($allCandidates as $candidate) {
            $roles = $getUserRoles($candidate);
            if ($hasRole('digital', $roles)) {
                return [
                    'key' => 'digital',
                    'role' => 'Digital',
                    'label' => 'Digital (App)',
                    'badge_text' => 'Digital',
                    'sub' => 'Aplikasi Mobile',
                    'color' => 'purple',
                    'effective_user' => $candidate,
                    'cart_user' => $cartUser ?? $candidate,
                    'tx_user' => $txUser,
                ];
            }
        }

        // 2. Online Shopee
        foreach ($allCandidates as $candidate) {
            $roles = $getUserRoles($candidate);
            if ($hasRole('online shopee', $roles) || $hasRole('shopee', $roles)) {
                return [
                    'key' => 'shopee',
                    'role' => 'Online Shopee',
                    'label' => 'Shopee',
                    'badge_text' => 'Shopee',
                    'sub' => 'Penjualan Shopee',
                    'color' => 'orange',
                    'effective_user' => $candidate,
                    'cart_user' => $cartUser ?? $candidate,
                    'tx_user' => $txUser,
                ];
            }
        }

        // 3. Online Grab
        foreach ($allCandidates as $candidate) {
            $roles = $getUserRoles($candidate);
            if ($hasRole('online grab', $roles) || $hasRole('grab', $roles)) {
                return [
                    'key' => 'grab',
                    'role' => 'Online Grab',
                    'label' => 'Grab',
                    'badge_text' => 'Grab',
                    'sub' => 'Penjualan Grab',
                    'color' => 'emerald',
                    'effective_user' => $candidate,
                    'cart_user' => $cartUser ?? $candidate,
                    'tx_user' => $txUser,
                ];
            }
        }

        // 4. Online (Chat WA)
        foreach ($allCandidates as $candidate) {
            $roles = $getUserRoles($candidate);
            if ($hasRole('online', $roles)) {
                return [
                    'key' => 'online',
                    'role' => 'Online',
                    'label' => 'Online (WA)',
                    'badge_text' => 'Online WA',
                    'sub' => 'Chat WA',
                    'color' => 'green',
                    'effective_user' => $candidate,
                    'cart_user' => $cartUser ?? $candidate,
                    'tx_user' => $txUser,
                ];
            }
        }

        // 5. Default / Offline Kasir
        $effective = $txUser ?? $cartUser ?? $allCandidates->first();
        return [
            'key' => 'kasir',
            'role' => $effective?->roles?->first()?->name ?? 'Kasir',
            'label' => 'Kasir (Offline)',
            'badge_text' => 'Kasir',
            'sub' => 'Penjualan Kasir',
            'color' => 'slate',
            'effective_user' => $effective,
            'cart_user' => $cartUser,
            'tx_user' => $txUser,
        ];
    }

    public static function renderChannelBadge($cartUser = null, $txUser = null, $extraUsers = [])
    {
        $info = self::resolveChannelInfo($cartUser, $txUser, $extraUsers);

        $onlineUser = $info['effective_user'];
        $cashierUser = ($info['tx_user'] && $onlineUser && $info['tx_user']->id !== $onlineUser->id) ? $info['tx_user'] : null;

        if ($info['key'] !== 'kasir' && $onlineUser) {
            $creator = "Order: " . ($onlineUser->name ?? $onlineUser->username);
            if ($cashierUser) {
                $creator .= " | Kasir: " . ($cashierUser->name ?? $cashierUser->username);
            }
        } else {
            $user = $info['effective_user'];
            $creator = $user ? "Kasir: " . ($user->name ?? $user->username) : "Penjualan Kasir";
        }
        $tooltip = e($creator . ' | ' . $info['sub']);

        switch ($info['key']) {
            case 'shopee':
                return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-orange-50 text-orange-700 border border-orange-200 shadow-sm" title="' . $tooltip . '">
                    <svg class="w-3.5 h-3.5 text-orange-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19.5 7.5h-2.25V6.75C17.25 3.85 14.9 1.5 12 1.5S6.75 3.85 6.75 6.75v.75H4.5A2.25 2.25 0 002.25 9.75v10.5A2.25 2.25 0 004.5 22.5h15a2.25 2.25 0 002.25-2.25V9.75a2.25 2.25 0 00-2.25-2.25zm-11.25-.75c0-2.07 1.68-3.75 3.75-3.75s3.75 1.68 3.75 3.75v.75h-7.5v-.75zm12.75 13.5H4.5V9.75h2.25v2.25a.75.75 0 001.5 0V9.75h7.5v2.25a.75.75 0 001.5 0V9.75h2.25v10.5z"/>
                    </svg>
                    <span>Shopee</span>
                </span>';

            case 'grab':
                return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-sm" title="' . $tooltip . '">
                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Grab</span>
                </span>';

            case 'digital':
                return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 shadow-sm" title="' . $tooltip . '">
                    <svg class="w-3.5 h-3.5 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>Digital</span>
                </span>';

            case 'online':
                return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-green-50 text-green-700 border border-green-200 shadow-sm" title="' . $tooltip . '">
                    <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span>Online WA</span>
                </span>';

            case 'kasir':
            default:
                return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200 shadow-sm" title="' . $tooltip . '">
                    <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Kasir (Offline)</span>
                </span>';
        }
    }
}
