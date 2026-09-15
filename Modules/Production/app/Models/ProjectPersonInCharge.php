<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\WhatsappGroup;
use Modules\Production\Database\Factories\ProjectPersonInChargeFactory;

class ProjectPersonInCharge extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'pic_id',
        'is_lead',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function whatsappGroupPic(): HasOne
    {
        return $this->hasOne(WhatsappGroup::class, 'employee_id', 'pic_id');
    }

    // protected static function newFactory(): ProjectPersonInChargeFactory
    // {
    //     //return ProjectPersonInChargeFactory::new();
    // }
}
