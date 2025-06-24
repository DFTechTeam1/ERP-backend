<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Production\Database\Factories\QuotationItemFactory;

// use Modules\Production\Database\Factories\QuotationItemFactory;

class QuotationItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
    ];

    protected static function newFactory(): QuotationItemFactory
    {
        return QuotationItemFactory::new();
    }
}
