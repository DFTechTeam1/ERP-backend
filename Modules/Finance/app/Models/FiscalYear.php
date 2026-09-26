<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\FiscalYear\FiscalStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Finance\Database\Factories\FiscalYearFactory;

class FiscalYear extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by'
    ];

    // protected static function newFactory(): FiscalYearFactory
    // {
    //     // return FiscalYearFactory::new();
    // }

    protected $casts = [
        'status' => FiscalStatus::class
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year_id');
    }
}
