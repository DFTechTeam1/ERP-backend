<?php

namespace Modules\Hrd\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\Models\Position;
use Modules\Hrd\Database\factories\EmployeeFactory;
use Modules\Inventory\Models\InventoryRequest;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Hrd\Observers\EmployeeObserver;
use Modules\Hrd\Observers\EmployeeObserverObserver;

// #[ObservedBy([EmployeeObserver::class])]
class Employee extends Model
{
    use HasFactory, ModelObserver, ModelCreationObserver, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'employee_id',
        'nickname',
        'email',
        'phone',
        'id_number',
        'religion',
        'martial_status',
        'address',
        'province_id',
        'city_id',
        'district_id',
        'village_id',
        'postal_code',
        'current_address',
        'blood_type',
        'date_of_birth',
        'place_of_birth',
        'dependant',
        'gender',
        'bank_detail',
        'relation_contact',
        'education',
        'education_name',
        'education_major',
        'education_year',
        'position_id',
        'boss_id',
        'level_staff',
        'status',
        'placement',
        'join_date',
        'start_review_probation_date',
        'probation_status',
        'end_probation_date',
        'company_name',
        'bpjs_status',
        'bpjs_ketenagakerjaan_number',
        'bpjs_kesehatan_number',
        'npwp_number',
        'bpjs_photo',
        'npwp_photo',
        'id_number_photo',
        'kk_photo',
        'created_by',
        'updated_by',
        'user_id'
    ];

    protected $appends = ['status_text', 'status_color'];

    public function statusText(): Attribute
    {
        $statuses = \App\Enums\Employee\Status::cases();

        $out = '-';

        if ($this->status) {
            foreach ($statuses as $status) {
                if ($status->value == $this->status) {
                    $out = $status->label();
                    break;
                }
            }
        }

        return new Attribute(
            get: fn() => $out,
        );
    }

    public function statusColor(): Attribute
    {
        $statuses = \App\Enums\Employee\Status::cases();

        $out = '-';

        if ($this->status) {
            foreach ($statuses as $status) {
                if ($status->value == $this->status) {
                    $out = $status->statusColor();
                    break;
                }
            }
        }

        return new Attribute(
            get: fn() => $out,
        );
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function boss()
    {
        return $this->belongsTo(Employee::class, 'boss_id', 'id');
    }

    public function idNumberPhoto(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value) ? asset('storage/employees/' . $value) : '',
        );
    }

    public function npwpPhoto(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value) ? asset('storage/employees/' . $value) : '',
        );
    }

    public function bpjsPhoto(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value) ? asset('storage/employees/' . $value) : '',
        );
    }

    public function kkPhoto(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value) ? asset('storage/employees/' . $value) : '',
        );
    }

//    public function inventory_requests()
//    {
//        return $this->hasMany(InventoryRequest::class, 'request_by', 'id');
//    }
}
