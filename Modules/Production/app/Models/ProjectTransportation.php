<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\ProjectTransportationFactory;

class ProjectTransportation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): ProjectTransportationFactory
    // {
    //     // return ProjectTransportationFactory::new();
    // }

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            related: ProjectTransportationDetail::class,
            foreignKey: 'project_transportation_id',
            localKey: 'id'
        );
    }
}
