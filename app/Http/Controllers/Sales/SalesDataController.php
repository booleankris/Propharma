<?php

namespace App\Http\Controllers\Sales;

use App\Exports\Report\SalesDataExport;
use App\Http\Controllers\Controller;
use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\Retur;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DataTables;
use Form;
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
                ->rawColumns(['final_price', 'channel'])
                ->make(true);
        }

        return view('sales.salesdata', [
            'restrictedChannel'     => $restrictedChannel,
            'restrictedRole'        => $restrictedRole,
            'restrictedChannelName' => $restrictedRole ? getOnlineChannelName() : null,
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
}
