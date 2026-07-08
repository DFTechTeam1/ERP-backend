<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Models\DivisionBackup;

// use Modules\Hrd\Database\Factories\DocumentTypeSignerFactory;

class DocumentTypeSigner extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'division_id',
        'order'
    ];

    // protected static function newFactory(): DocumentTypeSignerFactory
    // {
    //     // return DocumentTypeSignerFactory::new();
    // }

    public function division(): BelongsTo
    {
        return $this->belongsTo(DivisionBackup::class, 'division_id');
    }
}
