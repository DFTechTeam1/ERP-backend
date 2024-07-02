<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens, ModelObserver;

    protected $guard_name = 'sanctum';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'uid',
        'image',
        'last_login_at',
        'is_external_user',
        'username',
        'employee_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['status', 'status_color'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function lastLogin(): HasOne
    {
        return $this->hasOne(UserLoginHistory::class, 'user_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(\Modules\Hrd\Models\Employee::class, 'user_id');
    }

    public function status(): Attribute
    {
        $out = __('global.notYetVerified');
        if ($this->email_verified_at) {
            $out = __('global.verified');
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function statusColor(): Attribute
    {
        $out = 'grey-lighten-1';
        if ($this->email_verified_at) {
            $out = 'green-lighten-3';
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
