<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\Signature\SignatureTaskStatus;
use App\Enums\Hrd\Signature\Template\Status;
use App\Traits\ModelObserver;
use Database\Factories\Hrd\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class EmployeeDocument extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
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

    public function documentSnapshot(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id', 'id');
    }

    public function signatureTasks(): HasMany
    {
        return $this->hasMany(EmployeeSignatureTask::class, 'employee_document_id');
    }

    protected static function newFactory(): EmployeeDocumentFactory
    {
        return EmployeeDocumentFactory::new();
    }

    public function isMyTurnToSign(): bool
    {
        $output = false;

        if (! $this->relationLoaded('signatureTasks')) {
            $this->load('signatureTasks');
        }

        $employeeId = Auth::user()->employee_id ?? 0;
        $search = $this->signatureTasks->search(function ($item) use ($employeeId) {
            return $item->employee_id === $employeeId;
        });

        if (gettype($search) == 'integer') {
            if ($search === 0 && $this->signatureTasks[$search]->status === SignatureTaskStatus::Waiting) {
                $output = true;
            }
            if ($search > 0 && $this->signatureTasks[$search]->status == SignatureTaskStatus::Waiting && $this->signatureTasks[$search - 1]->status == SignatureTaskStatus::Signed) {
                $output = true;
            }
        }

        return $output;
    }
}
