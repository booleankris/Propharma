<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RejectFollowUpOption extends Model
{
    use HasFactory;

    protected $table = 'reject_follow_up_options';

    protected $fillable = [
        'pharmacy_id',
        'category',
        'label',
    ];

    public const CATEGORY_STOCK_STATUS = 'stock_status';
    public const CATEGORY_FOLLOW_UP = 'follow_up';
    public const CATEGORY_UPDATE = 'follow_up_update';

    public const DEFAULTS = [
        self::CATEGORY_STOCK_STATUS => ['RETUR ED'],
        self::CATEGORY_FOLLOW_UP => ['SUDAH DIPESAN'],
        self::CATEGORY_UPDATE => ['Sudah Datang'],
    ];

    public static function ensureTableExists(): void
    {
        try {
            if (!Schema::hasTable('reject_follow_up_options')) {
                Schema::create('reject_follow_up_options', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('pharmacy_id')->nullable()->index();
                    $table->string('category', 50)->index();
                    $table->string('label', 255);
                    $table->timestamps();
                });
            }
            if (Schema::hasTable('reject') && !Schema::hasColumn('reject', 'location')) {
                Schema::table('reject', function (Blueprint $table) {
                    $table->string('location', 30)->nullable()->after('reason');
                });
            }
        } catch (\Throwable $e) {
            // Silently ignore if schema check fails
        }
    }

    /**
     * Get merged options (defaults + DB custom options) per category.
     */
    public static function getOptionsByCategory(?int $pharmacyId = null): array
    {
        self::ensureTableExists();

        $customRecords = self::query()
            ->when($pharmacyId, function ($q) use ($pharmacyId) {
                $q->where(function ($sub) use ($pharmacyId) {
                    $sub->whereNull('pharmacy_id')
                        ->orWhere('pharmacy_id', $pharmacyId);
                });
            })
            ->orderBy('id', 'asc')
            ->get();

        $result = [];
        foreach (self::DEFAULTS as $cat => $defaults) {
            $items = [];
            foreach ($defaults as $defLabel) {
                $items[mb_strtoupper(trim($defLabel))] = [
                    'id' => null,
                    'label' => $defLabel,
                    'is_default' => true,
                ];
            }

            foreach ($customRecords->where('category', $cat) as $rec) {
                $clean = trim((string) $rec->label);
                if ($clean === '') {
                    continue;
                }
                $key = mb_strtoupper($clean);
                if (!isset($items[$key])) {
                    $items[$key] = [
                        'id' => $rec->id,
                        'label' => $clean,
                        'is_default' => false,
                    ];
                }
            }

            $result[$cat] = array_values($items);
        }

        return $result;
    }
}

