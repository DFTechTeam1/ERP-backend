<?php

namespace App\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormInteractive extends Model
{
    use HasFactory, ModelObserver;

    protected $fillable = [
        'uid',
        'name',
        'forms',
        'qrcode',
        'background',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(FormInteractiveResponse::class, 'form_interactive_id');
    }
}
