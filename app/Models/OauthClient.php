<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthClient extends Model
{
    use HasFactory;

    public $timestamps    = false;
    protected $primaryKey = 'client_id';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $fillable = [
        'client_id',
        'client_name',
        'redirect_uris',
        'client_id_issued_at',
    ];

    protected $casts = [
        'redirect_uris'       => 'array',
        'client_id_issued_at' => 'datetime',
    ];
}
