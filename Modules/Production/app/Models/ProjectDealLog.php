<?php

namespace Modules\Production\Models;

use App\Enums\Production\Deal\DealLogAction;
use App\Traits\ProjectDealLogObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProjectDealLogFactory;

class ProjectDealLog extends Model
{
    use HasFactory, ProjectDealLogObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'action',
        'project_deal_id',
        'description',
        'actor_name',
        'actor_role',
        'meta'
    ];

    // protected static function newFactory(): ProjectDealLogFactory
    // {
    //     // return ProjectDealLogFactory::new();
    // }

    protected $casts = [
        'action' => DealLogAction::class
    ];

    public function meta(): Attribute
    {
        return Attribute::make(
            get: fn($val) => $val ? json_decode($val, true) : [],
            set: fn($val) => $val ? json_encode($val) : null
        );
    }
}
