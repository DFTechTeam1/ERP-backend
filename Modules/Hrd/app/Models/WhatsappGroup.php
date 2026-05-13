<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Hrd\Database\Factories\WhatsappGroupFactory;

class WhatsappGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'group_id',
        'group_name'
    ];

    // protected static function newFactory(): WhatsappGroupFactory
    // {
    //     // return WhatsappGroupFactory::new();
    // }
}
