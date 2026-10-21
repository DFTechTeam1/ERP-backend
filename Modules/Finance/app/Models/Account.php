<?php

namespace Modules\Finance\Models;

use App\Enums\Accounting\AccountSubtype;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\CashFlowCategory;
use App\Enums\Accounting\NormalBalance;
use App\Models\User;
use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// use Modules\Finance\Database\Factories\AccountFactory;

class Account extends Model
{
    use HasFactory, ModelCreationObserver, ModelObserver, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'code',
        'name',
        'account_type',
        'account_subtype',
        'normal_balance',
        'parent_id',
        'level',
        'is_postable',
        'is_cash_equivalent',
        'cash_flow_category',
        'currency_code',
        'is_locked',
        'is_active',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'account_subtype' => AccountSubtype::class,
            'normal_balance' => NormalBalance::class,
            'cash_flow_category' => CashFlowCategory::class,
            'is_postable' => 'boolean',
            'is_cash_equivalent' => 'boolean',
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // protected static function newFactory(): AccountFactory
    // {
    //     // return AccountFactory::new();
    // }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
