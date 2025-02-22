<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Production\Models\Project;

// use Modules\Hrd\Database\Factories\EmployeePointProjectFactory;

class EmployeePointProject extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_point_id',
        'project_id',
        'total_point',
        'additional_point'
    ];

    // protected static function newFactory(): EmployeePointProjectFactory
    // {
    //     // return EmployeePointProjectFactory::new();
    // }

    protected $appends = ['point'];

    public function point(): Attribute
    {
        $output = 0;
        if (isset($this->attributes['total_point'])) {
            $output = $this->attributes['total_point'];

            if (isset($this->attributes['additional_point'])) {
                $output -=  $this->attributes['additional_point'];
            }
        }

        return Attribute::make(
            get: fn() => $output
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(EmployeePointProjectDetail::class, 'point_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
