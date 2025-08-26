<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Development\Database\Factories\DevelopmentProjectTaskPicFactory;

class DevelopmentProjectTaskPic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'development_project_id',
        'employee_id',
        'task_id'
    ];

    // protected static function newFactory(): DevelopmentProjectTaskPicFactory
    // {
    //     // return DevelopmentProjectTaskPicFactory::new();
    // }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class);
    }
}
