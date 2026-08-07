<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\Signature\DocumentType\Type;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// use Modules\Hrd\Database\Factories\DocumentTypeFactory;

class DocumentType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'retention',
        'default_number_of_signers',
        'status',
        'category',
        'created_by',
        'default_signers',
    ];

    protected function casts(): array
    {
        return [
            'retention' => 'integer',
            'default_number_of_signers' => 'integer',
            'status' => 'integer',
            'category' => Type::class,
        ];
    }

    public function defaultSigners(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value) : [],
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function masterDocuments(): HasMany
    {
        return $this->hasMany(MasterDocument::class, 'document_type_id', 'id');
    }

    public function masterDocument(): HasOne
    {
        return $this->hasOne(MasterDocument::class, 'document_type_id', 'id');
    }

    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'document_type_id', 'id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(DocumentTypeSigner::class, 'type_id');
    }

    // protected static function newFactory(): DocumentTypeFactory
    // {
    //     // return DocumentTypeFactory::new();
    // }
}
