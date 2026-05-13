<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Override;

// use Modules\Hrd\Database\Factories\WhatsappOtpFactory;

class WhatsappOtp extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'otp',
        'phone',
        'employee_id',
        'is_verified',
        'expired_date',
    ];

    #[Override]
    protected function casts()
    {
        return [
            'expired_date' => 'datetime'
        ];
    }

    // protected static function newFactory(): WhatsappOtpFactory
    // {
    //     // return WhatsappOtpFactory::new();
    // }
}
