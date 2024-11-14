<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'icon',
        'group',
        'link',
        'permission',
        'app_type',
        'new_icon',
        'new_link'
    ];
}
