<?php

namespace Modules\Company\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
// use Modules\Company\Database\Factories\DivisionBackupFactory;

class DivisionBackup extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'parent_id',
        'uid',
        'created_by',
        'updated_by',
    ];

    // protected static function newFactory(): DivisionBackupFactory
    // {
    //     // return DivisionBackupFactory::new();
    // }

    public function positions()
    {
        return $this->hasMany(PositionBackup::class, 'division_id', 'id');
    }

    public function parentDivision()
    {
        return $this->belongsTo(DivisionBackup::class, 'parent_id', 'id');
    }

    public function childDivisions()
    {
        return $this->hasMany(DivisionBackup::class, 'parent_id', 'id');
    }

    public function scopeFindByName(Builder $query, string $name)
    {
        return $query->whereRaw("LOWER(name) = '" . strtolower($name) . "'")
            ->first();
    }
}
