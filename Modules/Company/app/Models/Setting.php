<?php

namespace Modules\Company\Models;

use App\Traits\FlushCacheOnModelChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Company\Database\Factories\SettingFactory;

// use Modules\Company\Database\factories\SettingFactory;

class Setting extends Model
{
    use HasFactory, FlushCacheOnModelChange;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key', 'value', 'code'
    ];

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    public static function scopeGetIp(Builder $query)
    {
        $query->where('key', 'nas_current_ip');
    }

    public static function scopeGetRoot(Builder $query)
    {
        $query->where('key', 'nas_current_root');
    }
}
