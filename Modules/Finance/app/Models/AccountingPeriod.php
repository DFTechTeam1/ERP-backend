<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\FiscalYear\AccountingPeriodStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\AccountingPeriodFactory;

class AccountingPeriod extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'fiscal_year_id',
        'name',
        'period_number',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by'
    ];

    // protected static function newFactory(): AccountingPeriodFactory
    // {
    //     // return AccountingPeriodFactory::new();
    // }

    protected $casts = [
        'status' => AccountingPeriodStatus::class
    ];
}
