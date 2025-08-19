<?php

namespace Modules\Development\Models;

use App\Enums\Development\Project\ProjectStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

// use Modules\Development\Database\Factories\DevelopmentProjectFactory;

class DevelopmentProject extends Model
{
    use HasFactory, ModelObserver;

    protected static function booted()
    {
        static::creating(function (DevelopmentProject $model) {
            $model->created_by = Auth::id();
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'description',
        'status',
        'project_date',
        'created_by'
    ];

    // protected static function newFactory(): DevelopmentProjectFactory
    // {
    //     // return DevelopmentProjectFactory::new();
    // }

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'project_date' => 'datetime:Y-m-d'
        ];
    }

    public function references(): HasMany
    {
        return $this->hasMany(DevelopmentProjectReference::class);
    }

    public function pics(): HasMany
    {
        return $this->hasMany(DevelopmentProjectPic::class);
    }

}
