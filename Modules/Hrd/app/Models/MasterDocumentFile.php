<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\MasterDocumentFileFactory;

class MasterDocumentFile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'master_document_id',
        'path',
        'file_type',
        'placeholder_mapping',
        'version',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'placeholder_mapping' => 'array',
            'status' => 'integer',
        ];
    }

    public function masterDocument(): BelongsTo
    {
        return $this->belongsTo(MasterDocument::class, 'master_document_id', 'id');
    }

    // protected static function newFactory(): MasterDocumentFileFactory
    // {
    //     // return MasterDocumentFileFactory::new();
    // }
}
