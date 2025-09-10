<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Finance\Database\Factories\TransactionImageFactory;

class TransactionImage extends Model
{
    use HasFactory;

    const PATH = 'transactions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'transaction_id',
        'image',
    ];

    protected $appends = [
        'real_path',
    ];

    // protected static function newFactory(): TransactionImageFactory
    // {
    //     // return TransactionImageFactory::new();
    // }

    /**
     * Setup real path of the image
     */
    public function realPath(): Attribute
    {
        $output = null;

        if (isset($this->attributes['image'])) {
            $output = asset('storage/'.self::PATH.'/'.$this->attributes['image']);
        }

        return Attribute::make(
            get: fn () => $output
        );
    }
}
