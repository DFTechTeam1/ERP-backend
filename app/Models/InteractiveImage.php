<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteractiveImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'qrcode',
        'filepath',
        'identifier'
    ];
}
