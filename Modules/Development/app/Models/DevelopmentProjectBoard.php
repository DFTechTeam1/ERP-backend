<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Development\Database\Factories\DevelopmentProjectBoardFactory;

class DevelopmentProjectBoard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name'
    ];

    // protected static function newFactory(): DevelopmentProjectBoardFactory
    // {
    //     // return DevelopmentProjectBoardFactory::new();
    // }

    public function tasks(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTask::class);
    }
}
