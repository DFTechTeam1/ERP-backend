<?php

namespace Modules\Hrd\Models;

use App\Enums\Employee\Gender;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\RelationFamily;
use App\Enums\Employee\Religion;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFamily extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'employee_id',
        'name',
        'relationship',
        'address',
        'id_number',
        'gender',
        'date_of_birth',
        'religion',
        'martial_status',
        'job',
    ];

    protected $appends = [
        'gender_text',
        'religion_text',
        'martial_status_text',
        'relationship_text',
    ];

    public function genderText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['gender'])) {
            foreach (Gender::cases() as $case) {
                if ($case->value == $this->attributes['gender']) {
                    $out = $case->label();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn () => $out
        );
    }

    public function religionText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['religion'])) {
            foreach (Religion::cases() as $case) {
                if ($case->value == $this->attributes['religion']) {
                    $out = Religion::getReligion($case->value);
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn () => $out
        );
    }

    public function martialStatusText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['martial_status'])) {
            foreach (MartialStatus::cases() as $case) {
                if ($case->value == $this->attributes['martial_status']) {
                    $out = $case->label();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn () => $out
        );
    }

    public function relationshipText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['relationship'])) {
            foreach (RelationFamily::cases() as $case) {
                if ($case->value == $this->attributes['relationship']) {
                    $out = $case->label();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn () => $out
        );
    }
}
