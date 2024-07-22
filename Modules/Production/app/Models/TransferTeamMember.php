<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Production\Database\Factories\TransferTeamMemberFactory;

class TransferTeamMember extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'employee_id',
        'reason',
        'project_date',
        'status',
        'request_to',
        'requested_by',
        'request_at',
        'approved_at',
        'completed_at',
        'rejected_at',
        'canceled_at',
        'reject_reason',
    ];

    protected $appends = ['status_text', 'status_color'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'employee_id');
    }

    public function requestToPerson(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'request_to');
    }

    public function requestByPerson(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'requested_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\Project::class, 'project_id');
    }

    public function statusText(): Attribute
    {
        $out = '-';

        if (isset($this->attributes['status'])) {
            $statusses = \App\Enums\Production\TransferTeamStatus::cases();
            foreach ($statusses as $status) {
                if ($status->value == $this->attributes['status']) {
                    $out = $status->label();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }

    public function statusColor(): Attribute
    {
        $out = '-';

        if (isset($this->attributes['status'])) {
            $statusses = \App\Enums\Production\TransferTeamStatus::cases();
            foreach ($statusses as $status) {
                if ($status->value == $this->attributes['status']) {
                    $out = $status->color();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
