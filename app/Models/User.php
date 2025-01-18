<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens, ModelObserver, SoftDeletes;

    protected $guard_name = 'sanctum';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'email',
        'password',
        'is_external_user',
        'employee_id',
        'username',
        'is_employee',
        'is_director',
        'is_project_manager',
        'image',
        'last_login_at',
        'email_verified_at',
        'reset_password_token_claim',
        'reset_password_token_exp',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
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
        $out = 'secondary';
        if ($this->email_verified_at) {
            $out = 'primary';
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
