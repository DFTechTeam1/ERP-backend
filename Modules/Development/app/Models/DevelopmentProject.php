<?php

namespace Modules\Development\Models;

use App\Enums\Development\Project\ProjectStatus;
use App\Traits\ModelObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Modules\Development\Database\Factories\DevelopmentProjectFactory;

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
        'created_by',
    ];

    protected $appends = [
        'project_date_text',
    ];

    protected static function newFactory(): DevelopmentProjectFactory
    {
        return DevelopmentProjectFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'project_date' => 'datetime:Y-m-d',
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

    public function boards(): HasMany
    {
        return $this->hasMany(DevelopmentProjectBoard::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DevelopmentProjectTask::class);
    }

    public function projectDateText(): Attribute
    {
        $output = '-';

        if (isset($this->attributes['project_date'])) {
            $output = Carbon::parse($this->attributes['project_date'])->format('d F Y');
        }

        return Attribute::make(
            get: fn () => $output
        );
    }
}
