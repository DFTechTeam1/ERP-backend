<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Models\DivisionBackup;

// use Modules\Hrd\Database\Factories\OvertimeRateSettingFactory;

class OvertimeRateSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'division_id',
        'price_per_hour'
    ];

    // protected static function newFactory(): OvertimeRateSettingFactory
    // {
    //     // return OvertimeRateSettingFactory::new();
    // }

    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:12,2'
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(DivisionBackup::class, 'division_id');
    }
}
