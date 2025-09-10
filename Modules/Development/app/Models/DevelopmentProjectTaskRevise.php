<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Development\Database\Factories\DevelopmentProjectTaskReviseFactory;

class DevelopmentProjectTaskRevise extends Model
{
    use HasFactory;

    protected $table = 'dev_project_task_revises';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'task_id',
        'reason',
        'assigned_by',
    ];

    // protected static function newFactory(): DevelopmentProjectTaskReviseFactory
    // {
    //     // return DevelopmentProjectTaskReviseFactory::new();
    // }

    public function images(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTaskReviseImage::class, 'revise_id');
    }
}
