<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\Signature\Template\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\EmployeeDocumentFactory;

class EmployeeDocument extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'status',
        'signers_detail',
        'total_signer',
        'document_snapshot',
        'document_path',
        'document_type_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'signers_detail' => 'array',
            'total_signer' => 'integer',
            'document_snapshot' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'id');
    }

    // protected static function newFactory(): EmployeeDocumentFactory
    // {
    //     // return EmployeeDocumentFactory::new();
    // }
}
