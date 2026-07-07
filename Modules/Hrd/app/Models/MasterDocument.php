<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Hrd\Database\Factories\MasterDocumentFactory;

class MasterDocument extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'document_type_id',
        'current_active_version_text',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(MasterDocumentSigner::class, 'master_document_id', 'id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MasterDocumentFile::class, 'master_document_id', 'id');
    }

    // protected static function newFactory(): MasterDocumentFactory
    // {
    //     // return MasterDocumentFactory::new();
    // }
}
