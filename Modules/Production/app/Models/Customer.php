<?php

namespace Modules\Production\Models;

use App\Traits\FlushCacheOnModelChange;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Database\Factories\CustomerFactory;

// use Modules\Production\Database\Factories\CustomerFactory;

class Customer extends Model
{
    use FlushCacheOnModelChange, HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
    ];

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\Modules\Finance\Models\Invoice::class, 'customer_id');
    }
}
