<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Database\Factories\ProjectMarketingFactory;

class ProjectMarketing extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'marketing_id',
    ];

    public function marketing(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'marketing_id');
    }
}
