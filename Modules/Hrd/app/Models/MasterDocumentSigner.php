<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Models\PositionBackup;

// use Modules\Hrd\Database\Factories\MasterDocumentSignerFactory;

class MasterDocumentSigner extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'master_document_id',
        'position_id',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function masterDocument(): BelongsTo
    {
        return $this->belongsTo(MasterDocument::class, 'master_document_id', 'id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionBackup::class, 'position_id', 'id');
    }

    // protected static function newFactory(): MasterDocumentSignerFactory
    // {
    //     // return MasterDocumentSignerFactory::new();
    // }
}
