<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Company\Database\Factories\WhatsappLogFactory;

class WhatsappLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'to',
        'response',
        'text',
        'service_type',
        'action_type',
    ];

    // protected static function newFactory(): WhatsappLogFactory
    // {
    //     // return WhatsappLogFactory::new();
    // }

    protected function response(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => !$value ? [] : json_decode($value),
            set: fn ($value) => !$value ? [] : json_encode($value),
        );
    }
}
