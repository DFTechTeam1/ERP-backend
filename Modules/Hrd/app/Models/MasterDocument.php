<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// use Modules\Hrd\Database\Factories\MasterDocumentFactory;

class MasterDocument extends Model
{
    use HasFactory, ModelObserver;

    public static function booted(): void
    {
        static::creating(function (MasterDocument $model) {
            // versioning handle here
            $current = MasterDocument::select('id')
                ->where('document_type_id', $model->document_type_id)
                ->count();
            $model->current_active_version_text = 'version' . $current + 1;
        });
    }

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

    public function activeDocument(): HasOne
    {
        return $this->hasOne(MasterDocumentFile::class, 'master_document_id')
            ->where('status', DocumentFileStatus::Active);
    }

    public function isHavePendingReview(MasterDocument $model)
    {
        return (bool) $model->files->hasSole(fn ($item) => $item->status === DocumentFileStatus::PendingReview);
    }

    // protected static function newFactory(): MasterDocumentFactory
    // {
    //     // return MasterDocumentFactory::new();
    // }
}
