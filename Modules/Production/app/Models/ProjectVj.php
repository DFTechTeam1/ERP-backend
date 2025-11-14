<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectVj extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'employee_id',
        'note',
        'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\Project::class, 'project_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'created_by', 'id');
    }
}
