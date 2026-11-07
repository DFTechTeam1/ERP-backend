<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Company\Database\Factories\NotificationSettingFactory;

class NotificationSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'template',
        'action',
        'template_html',
        'notification_channel',
    ];

    // protected static function newFactory(): NotificationSettingFactory
    // {
    //     // return NotificationSettingFactory::new();
    // }

    public function notificationChannel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true),
            set: fn ($value) => json_encode($value),
        );
    }
}
