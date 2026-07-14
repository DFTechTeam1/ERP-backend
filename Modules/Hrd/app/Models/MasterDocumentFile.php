<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\MasterDocumentFileFactory;

class MasterDocumentFile extends Model
{
    use HasFactory;

    public static function booted(): void
    {
        static::creating(function (MasterDocumentFile $model) {
            $current = MasterDocumentFile::select('id')
                ->where('master_document_id', $model->master_document_id)
                ->count();

            $model->version = $current + 1;
        });
    }

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
        'created_by',
        'approved_by',
        'rejected_by',
        'approval_note',
    ];

    protected function casts(): array
    {
        return [
            'placeholder_mapping' => 'array',
            'status' => DocumentFileStatus::class,
        ];
    }

    public function placeholderMapping(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value) : [],
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function masterDocument(): BelongsTo
    {
        return $this->belongsTo(MasterDocument::class, 'master_document_id', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('status', DocumentFileStatus::Archived);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', DocumentFileStatus::Active);
    }

    public function scopeMakeArchived(Builder $query)
    {
        $query->update(['status' => DocumentFileStatus::Archived]);
    }

    // protected static function newFactory(): MasterDocumentFileFactory
    // {
    //     // return MasterDocumentFileFactory::new();
    // }
}
