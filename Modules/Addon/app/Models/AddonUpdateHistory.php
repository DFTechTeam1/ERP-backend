<?php

namespace Modules\Addon\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Addon\Database\Factories\AddonUpdateHistoryFactory;

class AddonUpdateHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'addon_id',
        'improvements',
        'created_by',
        'updated_by',
    ];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }

    // protected static function newFactory(): AddonUpdateHistoryFactory
    // {
    //     //return AddonUpdateHistoryFactory::new();
    // }
}
