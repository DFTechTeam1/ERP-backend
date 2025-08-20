<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;

// use Modules\Development\Database\Factories\DevelopmentProjectPicFactory;

class DevelopmentProjectPic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'development_project_id',
        'employee_id'
    ];

    // protected static function newFactory(): DevelopmentProjectPicFactory
    // {
    //     // return DevelopmentProjectPicFactory::new();
    // }

    public function developmentProject(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'development_project_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
