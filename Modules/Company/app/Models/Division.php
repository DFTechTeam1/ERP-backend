<?php

namespace Modules\Company\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Company\Database\factories\DivisionFactory;

class Division extends Model
{
    use HasFactory, ModelCreationObserver, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'parent_id',
        'created_by',
        'updated_by',
    ];

    public function positions()
    {
        return $this->hasMany(Position::class, 'division_id', 'id');
    }

    public function parentDivision()
    {
        return $this->belongsTo(Division::class, 'parent_id', 'id');
    }

    public function childDivisions()
    {
        return $this->hasMany(Division::class, 'parent_id', 'id');
    }

    public function scopeFindByName(Builder $query, string $name)
    {
        return $query->whereRaw("LOWER(name) = '" . strtolower($name) . "'")
            ->first();
    }

    // protected static function newFactory(): DivisionFactory
    // {
    //     //return DivisionFactory::new();
    // }
}
