<?php

namespace App\Http\Controllers\Sales;

use App\Exports\Report\SalesDataExport;
use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\Retur;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DataTables;
use Form;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SalesDataController extends Controller
{
    public function index(Request $request)
    {
        $restrictedRole    = getRestrictedOnlineRole();
        $restrictedChannel = getRestrictedOnlineChannelKey();

        if ($request->ajax()) {

            $search   = trim((string) $request->input('search.value'));
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            // Jika user dibatasi role online (Online, Online Shopee, Online Grab, Digital), kunci channel ke role mereka
            $channel  = $restrictedChannel ?: $request->input('channel');

            $query = MedicineCart::query()
                ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
                ->with(['transactions.patients', 'transactions.user.roles', 'transactions.transactions.user.roles', 'transactions', 'user.roles'])
                ->where('medicine_transactions.status', 1)
                ->where('medicine_cart.status', 1)
                ->whereHas('transactions', function ($transaction) {
                    $transaction->where('pharmacy_id', getActivePharmacyId());
                })
                ->selectRaw('
                transaction_id,
                MAX(medicine_cart.user_id) as user_id,
                MAX(medicine_transactions.transaction_code) as transaction_code,
                MAX(COALESCE(medicine_transactions.created_at, medicine_transactions.updated_at)) as updated_at,
                SUM(medicine_cart.discount) as totaldiscount,
                MAX(medicine_cart.status) as status,
                SUM(final_price) as final_price,
                SUM(total_price) as subtotal,
                MAX(medicine_transactions.discount) as cart_discount
            ')
                ->groupBy('transaction_id')
                ->orderBy('transaction_code');

            $applyChannel = function ($q, $roleName) {
                $q->where(function ($sub) use ($roleName) {
                    $sub->whereHas('user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    })->orWhereHas('transactions.user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    })->orWhereHas('transactions.transactions.user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    });
                });
            };

            // Filter Channel / Jenis Transaksi
            if ($restrictedRole) {
                $applyChannel($query, $restrictedRole);
            } elseif ($channel) {
                if ($channel === 'shopee') {
                    $applyChannel($query, 'Online Shopee');
                } elseif ($channel === 'grab') {
                    $applyChannel($query, 'Online Grab');
                } elseif ($channel === 'online') {
                    $applyChannel($query, 'Online');
                } elseif ($channel === 'digital') {
                    $applyChannel($query, 'Digital');
                } elseif ($channel === 'kasir') {
                    $query->where(function ($q) {
                        $q->whereDoesntHave('user.roles', function ($r) {
                            $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                        })->whereDoesntHave('transactions.user.roles', function ($r) {
                            $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                        })->whereDoesntHave('transactions.transactions.user.roles', function ($r) {
                            $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                        });
                    });
                }
            }

            // text search only
            if ($search) {
                $query->where(function ($q) use ($search) {
                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->whereYear('medicine_transactions.updated_at', $search);
                    } else {
                        $q->whereHas('transactions', function ($t) use ($search) {
                            $t->where('transaction_code', 'like', "%{$search}%");
                        })
                            ->orWhereHas('transactions.patients', function ($p) use ($search) {
                                $p->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('transactions.user', function ($u) use ($search) {
                                $u->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            })
                            ->orWhereHas('user', function ($u) use ($search) {
                                $u->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            });
                    }
                });
            }

            // If only one date was sent, treat it as a single-day filter
            if ($dateFrom && !$dateTo) {
                $dateTo = $dateFrom;
            } elseif ($dateTo && !$dateFrom) {
                $dateFrom = $dateTo;
            }

            if ($dateFrom || $dateTo) {
                try {
                    $startObj = $dateFrom ? Carbon::createFromFormat('d/m/Y', $dateFrom)->startOfDay()->setTime(7, 30, 0) : null;
                    $endObj   = $dateTo   ? Carbon::createFromFormat('d/m/Y', $dateTo)->startOfDay()->addDay()->setTime(7, 30, 0) : null;

                    $query->where(function ($q) use ($startObj, $endObj) {
                        $q->where(function ($main) use ($startObj, $endObj) {
                            if ($startObj) $main->where('medicine_transactions.created_at', '>=', $startObj);
                            if ($endObj)   $main->where('medicine_transactions.created_at', '<', $endObj);
                        })->orWhere(function ($legacy) use ($startObj, $endObj) {
                            $legacy->whereNull('medicine_transactions.created_at');
                            if ($startObj) $legacy->where('medicine_transactions.updated_at', '>=', $startObj);
                            if ($endObj)   $legacy->where('medicine_transactions.updated_at', '<', $endObj);
                        });
                    });
                } catch (\Exception $e) {
                }
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return Carbon::parse($row->updated_at)->format('d/m/Y');
                })
                ->addColumn('time', function ($row) {
                    return Carbon::parse($row->updated_at)->format('H:i:s');
                })
                ->addColumn('kwitansi_printed_at', fn ($row) => $row->transactions?->kwitansi_printed_at)
                ->addColumn('code', function ($row) {
                    return $row->transactions?->transaction_code ?? '-';
                })
                ->addColumn('channel', function ($row) {
                    $cartUsers = $row->transactions?->transactions?->pluck('user')->filter() ?? collect();
                    return MedicineTransactions::renderChannelBadge($row->user, $row->transactions?->user, $cartUsers);
                })
                ->addColumn('channel_name', function ($row) {
                    $cartUsers = $row->transactions?->transactions?->pluck('user')->filter() ?? collect();
                    return MedicineTransactions::resolveChannelInfo($row->user, $row->transactions?->user, $cartUsers)['label'];
                })
                ->addColumn('creator_name', function ($row) {
                    $cartUsers = $row->transactions?->transactions?->pluck('user')->filter() ?? collect();
                    $info = MedicineTransactions::resolveChannelInfo($row->user, $row->transactions?->user, $cartUsers);
                    $user = $info['effective_user'] ?? $row->user ?? $row->transactions?->user;
                    return $user ? ($user->name ?? $user->username) : '-';
                })
                ->addColumn('type', function ($row) {
                    $type = $row->transactions?->transaction_type ?? '-';

                    if ($type == "KREDIT") $type = "UK";
                    else if ($type == "RESEP TUNAI") $type = "UM";
                    else if ($type == "HV/OTC") $type = "HV";
                    else if ($type == "UPDS") $type = "UP";

                    return $type;
                })
                ->addColumn('name', function ($row) {
                    return $row->transactions?->patients?->name ?? '-';
                })
                ->addColumn('final_price', function ($row) {
                    return 'Rp ' . number_format($row->final_price - $row->transactions?->discount, 0, ',', '.');
                })
                ->addColumn('subtotal', function ($row) {
                    return 'Rp ' . number_format($row->subtotal, 0, ',', '.');
                })
                ->addColumn('totaldiscount', function ($row) {
                    return 'Rp ' . number_format($row->totaldiscount + $row->cart_discount, 0, ',', '.');
                })
                ->addColumn('payment_method', function ($row) {
                    return \App\Support\PaymentMethod::label(
                        $row->transactions?->payment_method,
                        $row->transactions?->transfer_bank_name
                    );
                })
                ->addColumn('raw_payment_method', function ($row) {
                    return strtoupper(trim((string) ($row->transactions?->payment_method ?? 'CASH')));
                })
                ->addColumn('raw_bank_name', function ($row) {
                    return $row->transactions?->transfer_bank_name ?? '';
                })
                ->addColumn('raw_user_id', function ($row) {
                    return $row->transactions?->user_id ?? $row->user_id;
                })
                ->rawColumns(['final_price', 'channel'])
                ->make(true);
        }

        $user = auth()->user();
        $canEditPayment = $user && ($user->hasRole('General Manager') || $user->hasRole('administrator'));
        $pharmacyId = getActivePharmacyId();

        $availableBanks = FinanceAccount::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->where('account_type', 'bank')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'account_number'])
            ->map(function ($acc) {
                $bankClean = preg_replace('/^Rekening\s+/i', '', trim($acc->name));
                return [
                    'id' => $acc->id,
                    'name' => $acc->name,
                    'bank_name' => $bankClean,
                    'account_number' => $acc->account_number,
                ];
            });

        $cashiers = User::with('pharmacy')
            ->orderByRaw("CASE WHEN pharmacy_id = ? THEN 0 WHEN pharmacy_id IS NULL THEN 1 ELSE 2 END", [$pharmacyId])
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'pharmacy_id'])
            ->groupBy(function ($u) use ($pharmacyId) {
                if ($u->pharmacy_id == $pharmacyId) {
                    return 'Cabang Ini (' . ($u->pharmacy->name ?? 'Cabang') . ')';
                }
                return $u->pharmacy?->name ?? 'Pusat / Lainnya';
            });

        return view('sales.salesdata', [
            'restrictedChannel'     => $restrictedChannel,
            'restrictedRole'        => $restrictedRole,
            'restrictedChannelName' => $restrictedRole ? getOnlineChannelName() : null,
            'canEditPayment'        => $canEditPayment,
            'availableBanks'        => $availableBanks,
            'cashiers'              => $cashiers,
        ]);
    }

    public function export(Request $request)
    {
        $pharmacyId        = getActivePharmacyId();
        $restrictedRole    = getRestrictedOnlineRole();
        $restrictedChannel = getRestrictedOnlineChannelKey();

        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        // Jika user dibatasi role online, paksa channel sesuai role mereka
        $channel    = $restrictedChannel ?: $request->input('channel');
        $search     = $request->input('search');
        $mode       = $request->input('mode', 'all');

        $pharmacy = Pharmacies::find($pharmacyId);
        $pharmacyName = $pharmacy?->name ?? 'APOTEK';

        $safePharmacy = $pharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $pharmacy->name) . '_' : '';
        $modeSuffix = $mode === 'all' ? 'Lengkap' : ucfirst($mode);
        $channelSuffix = $restrictedChannel ? '_' . ucfirst($restrictedChannel) : ($channel ? '_' . ucfirst($channel) : '');
        $fileName = 'Data_Penjualan_' . $safePharmacy . $modeSuffix . $channelSuffix . '_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new SalesDataExport(
                $pharmacyId,
                $dateFrom,
                $dateTo,
                $channel,
                $search,
                $mode,
                $pharmacyName
            ),
            $fileName
        );
    }

    public function pending(Request $request)
    {
        $restrictedRole    = getRestrictedOnlineRole();
        $restrictedChannel = getRestrictedOnlineChannelKey();

        if ($request->ajax()) {

            $search   = trim((string) $request->input('search.value'));
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            // Jika user dibatasi role online, paksa channel sesuai role mereka
            $channel  = $restrictedChannel ?: $request->input('channel');

            $query = MedicineCart::query()
                ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
                ->with(['transactions.patients', 'transactions.user.roles', 'transactions.transactions.user.roles', 'transactions', 'user.roles'])
                ->whereHas('transactions', function ($transaction) {
                    $transaction->where('pharmacy_id', getActivePharmacyId())
                        ->where('status', 0);
                })
                ->selectRaw('
                transaction_id,
                MAX(medicine_cart.user_id) as user_id,
                MAX(medicine_transactions.transaction_code) as transaction_code,
                MAX(COALESCE(medicine_transactions.updated_at, medicine_cart.updated_at)) as updated_at,
                SUM(medicine_cart.discount) as totaldiscount,
                MAX(medicine_cart.status) as status,
                SUM(final_price) as final_price,
                SUM(total_price) as subtotal,
                MAX(medicine_transactions.discount) as cart_discount
            ')
                ->groupBy('transaction_id')
                ->orderBy('transaction_code');

            $applyChannel = function ($q, $roleName) {
                $q->where(function ($sub) use ($roleName) {
                    $sub->whereHas('user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    })->orWhereHas('transactions.user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    })->orWhereHas('transactions.transactions.user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    });
                });
            };

            // Filter Channel / Jenis Transaksi
            if ($restrictedRole) {
                $applyChannel($query, $restrictedRole);
            } elseif ($channel) {
                if ($channel === 'shopee') {
                    $applyChannel($query, 'Online Shopee');
                } elseif ($channel === 'grab') {
                    $applyChannel($query, 'Online Grab');
                } elseif ($channel === 'online') {
                    $applyChannel($query, 'Online');
                } elseif ($channel === 'digital') {
                    $applyChannel($query, 'Digital');
                } elseif ($channel === 'kasir') {
                    $query->where(function ($q) {
                        $q->whereDoesntHave('user.roles', function ($r) {
                            $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                        })->whereDoesntHave('transactions.user.roles', function ($r) {
                            $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                        })->whereDoesntHave('transactions.transactions.user.roles', function ($r) {
                            $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                        });
                    });
                }
            }

            // text search only
            if ($search) {
                $query->where(function ($q) use ($search) {
                    if (preg_match('/^\d{4}$/', $search)) {
                        $q->whereYear('medicine_cart.updated_at', $search);
                    } else {
                        $q->whereHas('transactions', function ($t) use ($search) {
                            $t->where('transaction_code', 'like', "%{$search}%");
                        })
                            ->orWhereHas('transactions.patients', function ($p) use ($search) {
                                $p->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('transactions.user', function ($u) use ($search) {
                                $u->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            })
                            ->orWhereHas('user', function ($u) use ($search) {
                                $u->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            });
                    }
                });
            }

            // If only one date was sent, treat it as a single-day filter
            // If only one date was sent, treat it as a single-day filter
            if ($dateFrom && !$dateTo) {
                $dateTo = $dateFrom;
            } elseif ($dateTo && !$dateFrom) {
                $dateFrom = $dateTo;
            }

            if ($dateFrom || $dateTo) {
                try {
                    $startObj = $dateFrom ? Carbon::createFromFormat('d/m/Y', $dateFrom)->startOfDay()->setTime(7, 30, 0) : null;
                    $endObj   = $dateTo   ? Carbon::createFromFormat('d/m/Y', $dateTo)->startOfDay()->addDay()->setTime(7, 30, 0) : null;

                    $query->where(function ($q) use ($startObj, $endObj) {
                        $q->where(function ($main) use ($startObj, $endObj) {
                            if ($startObj) $main->where('medicine_cart.created_at', '>=', $startObj);
                            if ($endObj)   $main->where('medicine_cart.created_at', '<', $endObj);
                        })->orWhere(function ($legacy) use ($startObj, $endObj) {
                            $legacy->whereNull('medicine_cart.created_at');
                            if ($startObj) $legacy->where('medicine_cart.updated_at', '>=', $startObj);
                            if ($endObj)   $legacy->where('medicine_cart.updated_at', '<', $endObj);
                        });
                    });
                } catch (\Exception $e) {
                }
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('date', function ($row) {
                    return Carbon::parse($row->updated_at)->format('d/m/Y');
                })
                ->addColumn('time', function ($row) {
                    return Carbon::parse($row->updated_at)->format('H:i:s');
                })
                ->addColumn('code', function ($row) {
                    return $row->transactions?->transaction_code ?? '-';
                })
                ->addColumn('channel', function ($row) {
                    $cartUsers = $row->transactions?->transactions?->pluck('user')->filter() ?? collect();
                    return MedicineTransactions::renderChannelBadge($row->user, $row->transactions?->user, $cartUsers);
                })
                ->addColumn('channel_name', function ($row) {
                    $cartUsers = $row->transactions?->transactions?->pluck('user')->filter() ?? collect();
                    return MedicineTransactions::resolveChannelInfo($row->user, $row->transactions?->user, $cartUsers)['label'];
                })
                ->addColumn('creator_name', function ($row) {
                    $cartUsers = $row->transactions?->transactions?->pluck('user')->filter() ?? collect();
                    $info = MedicineTransactions::resolveChannelInfo($row->user, $row->transactions?->user, $cartUsers);
                    $user = $info['effective_user'] ?? $row->user ?? $row->transactions?->user;
                    return $user ? ($user->name ?? $user->username) : '-';
                })
                ->addColumn('type', function ($row) {
                    $type = $row->transactions?->transaction_type ?? '-';

                    if ($type == "KREDIT") $type = "UK";
                    else if ($type == "RESEP TUNAI") $type = "UM";
                    else if ($type == "HV/OTC") $type = "HV";
                    else if ($type == "UPDS") $type = "UP";

                    return $type;
                })
                ->addColumn('type_slug', function ($row) {
                    $dbType = $row->transactions?->transaction_type ?? '';
                    return match ($dbType) {
                        'RESEP TUNAI' => 'resep',
                        'KREDIT' => 'kredit',
                        'UPDS' => 'upds',
                        'HV/OTC' => 'hv',
                        default => 'resep',
                    };
                })
                ->addColumn('name', function ($row) {
                    return $row->transactions?->patients?->name ?? '-';
                })
                ->addColumn('final_price', function ($row) {
                    return 'Rp ' . number_format($row->final_price - $row->transactions?->discount, 0, ',', '.');
                })
                ->addColumn('subtotal', function ($row) {
                    return 'Rp ' . number_format($row->subtotal, 0, ',', '.');
                })
                ->addColumn('totaldiscount', function ($row) {
                    return 'Rp ' . number_format($row->totaldiscount + $row->cart_discount, 0, ',', '.');
                })
                ->addColumn('payment_method', function ($row) {
                    return \App\Support\PaymentMethod::label(
                        $row->transactions?->payment_method,
                        $row->transactions?->transfer_bank_name
                    );
                })
                ->rawColumns(['final_price', 'channel'])
                ->make(true);
        }

        return view('sales.pending', [
            'restrictedChannel'     => $restrictedChannel,
            'restrictedRole'        => $restrictedRole,
            'restrictedChannelName' => $restrictedRole ? getOnlineChannelName() : null,
        ]);
    }

    public function transactionItems($transactionId)
    {
        $items = MedicineCart::with('medicine')
            ->where('transaction_id', $transactionId);
        return DataTables::of($items)
            ->addIndexColumn()

            ->addColumn('medicine', function ($row) {
                return $row->medicine?->name ?? '-';
            })

            ->addColumn('price', function ($row) {
                return 'Rp ' . number_format($row->item_price, 0, ',', '.');
            })

            ->addColumn('total', function ($row) {
                return 'Rp ' . number_format($row->final_price, 0, ',', '.');
            })

            ->make(true);
    }

    public function updatePayment(Request $request, $id)
    {
        $currentUser = auth()->user();
        if (!$currentUser || (!$currentUser->hasRole('General Manager') && !$currentUser->hasRole('administrator'))) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Fitur ini hanya dapat diakses oleh akun General Manager.',
            ], 403);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:CASH,QRIS,TRANSFER,DEBIT,BELUM BAYAR',
            'bank_name'      => 'nullable|string|max:50',
            'user_id'        => 'required|exists:users,id',
        ]);

        $newPaymentMethod = strtoupper(trim($validated['payment_method']));
        $newBankName = in_array($newPaymentMethod, ['QRIS', 'TRANSFER', 'DEBIT'], true)
            ? trim((string) ($validated['bank_name'] ?? ''))
            : null;

        if (in_array($newPaymentMethod, ['QRIS', 'TRANSFER', 'DEBIT'], true) && empty($newBankName)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama bank wajib dipilih untuk pembayaran non-tunai (' . $newPaymentMethod . ').',
            ], 422);
        }

        $newUserId = (int) $validated['user_id'];

        return DB::transaction(function () use ($id, $newPaymentMethod, $newBankName, $newUserId, $currentUser) {
            $transaction = MedicineTransactions::lockForUpdate()->findOrFail($id);
            $pharmacyId = (int) $transaction->pharmacy_id;
            $totalAmount = round((float) ($transaction->subtotal - ($transaction->discount ?? 0)), 2);

            // Reconcile FinancePayment & Account Balances jika bukan KREDIT
            if (strtoupper((string) $transaction->transaction_type) !== 'KREDIT') {
                $existingPayment = FinancePayment::where('medicine_transaction_id', $transaction->id)->lockForUpdate()->first();

                // 1. Rollback saldo dari akun lama jika sebelumnya ada finance payment
                if ($existingPayment && $existingPayment->account_id) {
                    $oldAccount = FinanceAccount::where('id', $existingPayment->account_id)->lockForUpdate()->first();
                    if ($oldAccount) {
                        $oldAccount->decrement('balance', $existingPayment->amount);
                    }
                }

                // 2. Tentukan akun keuangan baru berdasarkan metode pembayaran baru
                $newTargetAccount = null;
                $newPaymentType = null;
                $newNotes = null;

                if ($newPaymentMethod === 'CASH') {
                    $newTargetAccount = FinanceAccount::where('pharmacy_id', $pharmacyId)
                        ->where('category', 'Kas & Bank')
                        ->where('account_type', 'kas_apotek')
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();
                    $newPaymentType = 'PENJUALAN TUNAI';
                    $newNotes = 'Penerimaan penjualan tunai kasir (diedit oleh ' . $currentUser->name . ')';
                } elseif (in_array($newPaymentMethod, ['QRIS', 'TRANSFER', 'DEBIT'], true)) {
                    $normalizeBankName = static fn ($value) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
                    $bankKey = $normalizeBankName($newBankName);
                    $newTargetAccount = FinanceAccount::where('pharmacy_id', $pharmacyId)
                        ->where('category', 'Kas & Bank')
                        ->where('account_type', 'bank')
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->get()
                        ->first(function ($account) use ($normalizeBankName, $bankKey) {
                            return $bankKey !== '' && str_contains($normalizeBankName($account->name), $bankKey);
                        });

                    if (!$newTargetAccount) {
                        return response()->json([
                            'success' => false,
                            'message' => "Rekening bank {$newBankName} tidak ditemukan atau belum aktif di cabang ini.",
                        ], 422);
                    }

                    $newPaymentType = 'PENJUALAN NON TUNAI';
                    $newNotes = "Penerimaan penjualan {$newPaymentMethod} melalui {$newBankName} (diedit oleh {$currentUser->name})";
                }

                // 3. Update atau create FinancePayment record
                if ($newTargetAccount) {
                    $amount = $existingPayment ? $existingPayment->amount : $totalAmount;
                    if ($existingPayment) {
                        $existingPayment->update([
                            'account_id' => $newTargetAccount->id,
                            'payment_type' => $newPaymentType,
                            'notes' => $newNotes,
                        ]);
                    } else {
                        FinancePayment::create([
                            'pharmacy_id' => $pharmacyId,
                            'medicine_transaction_id' => $transaction->id,
                            'account_id' => $newTargetAccount->id,
                            'payment_type' => $newPaymentType,
                            'payment_date' => Carbon::parse($transaction->created_at ?? $transaction->updated_at)->toDateString(),
                            'amount' => $amount,
                            'reference_number' => $transaction->transaction_code,
                            'notes' => $newNotes,
                            'created_by' => $currentUser->id,
                        ]);
                    }
                    $newTargetAccount->increment('balance', $amount);
                } elseif ($newPaymentMethod === 'BELUM BAYAR' && $existingPayment) {
                    $existingPayment->delete();
                }

                Cache::forget('finance_stats_summary_branch_' . $pharmacyId . '_' . $pharmacyId);
            }

            // 4. Update data transaksi
            $transaction->update([
                'payment_method' => $newPaymentMethod,
                'transfer_bank_name' => $newBankName,
                'user_id' => $newUserId,
            ]);

            // 5. Update user_id di medicine_cart agar konsisten
            MedicineCart::where('transaction_id', $transaction->id)->update([
                'user_id' => $newUserId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Metode pembayaran dan kasir transaksi berhasil diperbarui.',
                'data' => [
                    'id' => $transaction->id,
                    'transaction_code' => $transaction->transaction_code,
                    'payment_method' => $transaction->payment_method,
                    'transfer_bank_name' => $transaction->transfer_bank_name,
                    'user_id' => $transaction->user_id,
                ],
            ]);
        });
    }
}
