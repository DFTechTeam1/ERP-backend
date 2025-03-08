<?php

namespace Modules\Company\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Hrd\Models\Employee;

// use Modules\Company\Database\Factories\PositionBackupFactory;

class PositionBackup extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'division_id',
        'uid',
        'created_by',
        'updated_by',
    ];

    // protected static function newFactory(): PositionBackupFactory
    // {
    //     // return PositionBackupFactory::new();
    // }

    /**
     * Position belongs to division
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function division()
    {
        return $this->belongsTo(DivisionBackup::class, 'division_id', 'id');
    }

    /**
     * Position has many employees
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id', 'id');
    }
}
