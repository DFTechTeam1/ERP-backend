<?php

namespace Modules\Hrd\Models;

use App\Enums\Employee\Gender;
use App\Enums\Employee\LevelStaff;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\PtkpStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\SalaryType;
use App\Enums\Employee\Status;
use App\Traits\FlushCacheOnModelChange;
use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Carbon\Carbon;
use Database\Factories\Hrd\EmployeeFactory as HrdEmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Company\Models\Position;
use Modules\Hrd\Database\factories\EmployeeFactory;
use Modules\Inventory\Models\InventoryRequest;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use KodePandai\Indonesia\Models\Village;
use Modules\Hrd\Observers\EmployeeObserver;
use Modules\Hrd\Observers\EmployeeObserverObserver;
use Illuminate\Notifications\Notifiable;
use Modules\Company\Models\Branch;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;
use Modules\Company\Models\IndonesiaVillage;
use Modules\Company\Models\JobLevel;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Models\Province;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\ProjectTaskPic;

// #[ObservedBy([EmployeeObserver::class])]
class Employee extends Model
{
    use HasFactory, ModelObserver, ModelCreationObserver, SoftDeletes, Notifiable, FlushCacheOnModelChange;

    protected static function newFactory()
    {
        return HrdEmployeeFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'religion' => Religion::class,
            'status' => Status::class,
            'martial_status' => MartialStatus::class,
            //'salary_type' => SalaryType::class,
            'ptkp_status' => PtkpStatus::class
        ];
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'nickname',
        'email',
        'date_of_birth',
        'place_of_birth',
        'martial_status',
        'religion',
        'phone',
        'id_number',
        'address',
        'current_address',
        'position_id',
        'employee_id',
        'level_staff',
        'boss_id',
        'status',
        'branch_id',
        'join_date',
        'gender',

        // new
        'ptkp_status',
        'basic_salary',
        'salary_type',

        'bpjs_ketenagakerjaan_number',
        'npwp_number',

        'province_id',
        'city_id',
        'district_id',
        'village_id',
        'postal_code',
        'blood_type',
        'bank_detail',
        'education',
        'education_name',
        'education_major',
        'education_year',
        'relation_contact',
        'start_review_probation_date',
        'end_probation_date',
        'probation_status',
        'bpjs_status',
        'bpjs_kesehatan_number',
        'bpjs_photo',
        'npwp_photo',
        'id_number_photo',
        'kk_photo',

        // 'placement',
        // 'dependant',
        // 'company_name',

        'created_by',
        'updated_by',
        'user_id',
        'line_id',
        'end_date',
        'resign_reason',
        'telegram_chat_id',
        'job_level_id',
        'is_sync_with_talenta',
        'avatar_color',

        'talenta_user_id',
    ];

    protected $appends = [
        'status_text',
        'status_color',
        'date_of_birth_text',
        'full_address',
        'initial',
        'gender_text',
        'martial_text',
        'blood_type_text',
        'religion_text',
        'length_of_service_year'
    ];

    /**
     * Determines how long to work in years
     *
     * @return Attribute
     */
    public function lengthOfServiceYear(): Attribute
    {
        $out = 0;
        if (isset($this->attributes['join_date'])) {
            $joinDate = Carbon::parse($this->attributes['join_date']);

            $out = Carbon::now()->diffInYears($joinDate);
            $out = number_format(num: $joinDate->diffInYears(Carbon::now()), decimals: 1);
        }

        return Attribute::make(
            get: fn() => (float) $out
        );
    }

    public function bloodTypeText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['blood_type'])) {
            $cases = \App\Enums\Employee\BloodType::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->attributes['blood_type']) {
                    $out = $case->value;
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function religionText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['religion'])) {
            $cases = \App\Enums\Employee\Religion::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->attributes['religion']) {
                    $out = Religion::getReligion($this->attributes['religion']);
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function genderText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['gender'])) {
            $cases = \App\Enums\Employee\Gender::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->attributes['gender']) {
                    $out = $case->label();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function martialText(): Attribute
    {
        $out = '-';

        if (isset($this->attributes['martial_status'])) {
            $cases = \App\Enums\Employee\MartialStatus::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->attributes['martial_status']) {
                    $out = $case->label();
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function initial(): Attribute
    {
        $out = '';
        if ($this->name) {
            $exp = explode(' ', $this->name);
            $formatted = count($exp) > 2 ? array_splice($exp, 0, 2) : $exp;
            foreach ($formatted as $name) {
                $out .= mb_substr($name, 0, 1);
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function statusText(): Attribute
    {
        $statuses = \App\Enums\Employee\Status::cases();

        $out = '-';

        if (isset($this->attributes['status'])) {
            foreach ($statuses as $status) {
                if ($status->value == $this->attributes['status']) {
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

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTaskPic::class, 'employee_id', 'id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionBackup::class, 'position_id', 'id');
    }

    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class, 'job_level_id');
    }

    public function families(): HasMany
    {
        return $this->hasMany(EmployeeFamily::class, 'employee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function boss()
    {
        return $this->belongsTo(Employee::class, 'boss_id', 'id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(\Modules\Production\Models\ProjectPersonInCharge::class, 'pic_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id', 'code');
    }

    public function points(): HasMany
    {
        return $this->hasMany(EmployeePoint::class, 'employee_id');
    }

    public function songTasks(): HasMany
    {
        return $this->hasMany(EntertainmentTaskSong::class, 'employee_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(IndonesiaCity::class, 'city_id', 'code');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(IndonesiaDistrict::class, 'district_id', 'code');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(IndonesiaVillage::class, 'village_id', 'code');
    }

    public function fullAddress(): Attribute
    {
        $out = '-';
        if ($this->address) {
            $out = $this->address;

            if ($this->village_id) {
                $village = Village::select('name')
                    ->find($this->village_id);

                $out .= ", " . $village->name;
            }

            if ($this->district_id) {
                $district = \KodePandai\Indonesia\Models\District::select('name')
                    ->find($this->district_id);

                $out .= ", " . $district->name;
            }

            if ($this->city_id) {
                $city = \KodePandai\Indonesia\Models\City::select("name")
                    ->find($this->city_id);

                $out .= ", " . $city->name;
            }

            if ($this->province_id) {
                $province = \KodePandai\Indonesia\Models\Province::select('name')
                    ->find($this->province_id);

                $out .= ", " . $province->name;
            }

            if ($this->postal_code) {
                $out .= " " . $this->postal_code;
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function dateOfBirthText(): Attribute
    {
        $out = '-';
        if ($this->date_of_birth) {
            $out = date('d F Y', strtotime($this->date_of_birth));
        }

        return Attribute::make(
            get: fn () => $out
        );
    }

    public function idNumberPhoto(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value) ? asset('storage/employees/' . $value) : '',
        );
    }

    public function bankDetail(): Attribute
    {
        return Attribute::make(
            get: fn($value) => ($value) ? json_decode($value, true) : [],
            set: fn($value) => $value ? json_encode($value) : NULL
        );
    }

    public function relationContact(): Attribute
    {
        return Attribute::make(
            get: fn($value) => ($value) ? json_decode($value, true) : [],
            set: fn($value) => $value ? json_encode($value) : NULL
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
