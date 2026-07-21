<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\Signature\SignatureTaskStatus;
use Database\Factories\Hrd\EmployeeSignatureTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSignatureTask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'employee_document_id',
        'employee_signature_id',
        'order',
        'status',
        'signed_at',
        'otp',
        'otp_expired_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SignatureTaskStatus::class,
            'signed_at' => 'datetime',
            'otp_expired_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EmployeeSignatureTaskFactory
    {
        return EmployeeSignatureTaskFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }

    public function employeeSignature(): BelongsTo
    {
        return $this->belongsTo(EmployeeSignature::class, 'employee_signature_id');
    }
}
