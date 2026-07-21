<?php

namespace Modules\Hrd\Models;

use App\Traits\ModelObserver;
use Database\Factories\Hrd\EmployeeSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSignature extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'employee_id',
        'is_active',
        'sign_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    protected static function newFactory(): EmployeeSignatureFactory
    {
        return EmployeeSignatureFactory::new();
    }
}
