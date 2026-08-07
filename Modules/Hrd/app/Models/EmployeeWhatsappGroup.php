<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Hrd\Database\Factories\EmployeeWhatsappGroupFactory;

class EmployeeWhatsappGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'group_id',
        'is_admin',
    ];

    // protected static function newFactory(): EmployeeWhatsappGroupFactory
    // {
    //     // return EmployeeWhatsappGroupFactory::new();
    // }

    public function parentGroup(): BelongsTo
    {
        return $this->belongsTo(WhatsappGroup::class, 'group_id', 'group_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
