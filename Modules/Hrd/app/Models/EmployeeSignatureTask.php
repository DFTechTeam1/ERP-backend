<?php

namespace Modules\Hrd\Models;

use App\Enums\Hrd\Signature\SignatureTaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\EmployeeSignatureTaskFactory;

class EmployeeSignatureTask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'employee_document_id',
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

    // protected static function newFactory(): EmployeeSignatureTaskFactory
    // {
    //     // return EmployeeSignatureTaskFactory::new();
    // }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }
}
