<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormInteractiveResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_interactive_id',
        'response',
    ];
}
