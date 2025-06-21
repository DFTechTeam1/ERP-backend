<?php

namespace Modules\Company\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Company\Database\Factories\PositionFactory;
use Modules\Hrd\Models\Employee;

// use Modules\Company\Database\factories\PositionFactory;

class Position extends Model
{
    use HasFactory, ModelCreationObserver, ModelObserver;

    protected static function newFactory()
    {
        return PositionFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'division_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Position belongs to division
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }

    /**
     * Position has many employees
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id', 'id');
    }

    // protected static function newFactory(): PositionFactory
    // {
    //     //return PositionFactory::new();
    // }
}
