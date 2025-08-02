<?php

namespace App\Models\Python;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'last_used_at',
        'access_token',
        'refresh_token',
        'created_at'
    ];
}
