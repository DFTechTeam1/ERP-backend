<?php

namespace Modules\Production\Models;

use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Enums\Production\ProjectDealChangeStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Finance\Models\Transaction;
use Modules\Production\Database\Factories\ProjectDealFactory;

// use Modules\Production\Database\Factories\ProjectDealFactory;

class ProjectDeal extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        // client portal
        // classification
        // city_name
        'project_date',
        'customer_id',
        'event_type',
        'venue',
        'collaboration',
        'note',
        'led_area',
        'led_detail',
        'interactive_area',
        'interactive_detail',
        'interactive_note',
        'country_id',
        'state_id',
        'city_id',
        'project_class_id',
        'longitude',
        'latitude',
        'equipment_type',
        'is_high_season',
        'status',
        'is_fully_paid',
        'cancel_reason',
        'cancel_at',
        'cancel_by',
        'identifier_number',
        'deleted_at',
        'is_have_interactive_element',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProjectDeal $projectDeal) {
            // get current identifier number from cache
            $currentIdentifier = (new \App\Services\GeneralService)->generateDealIdentifierNumber();
            $projectDeal->identifier_number = $currentIdentifier;

            // increase value of the identifier number
            (new \App\Services\GeneralService)->clearCache(cacheId: \App\Enums\Cache\CacheKey::ProjectDealIdentifierNumber->value);
            $nextIdentifier = (int) $currentIdentifier + 1;
            // convert to sequence number
            $lengthOfSentence = strlen($nextIdentifier) < 4 ? 4 : strlen($nextIdentifier) + 1;
            $nextIdentifier = (new \App\Services\GeneralService)->generateSequenceNumber(number: $nextIdentifier, length: $lengthOfSentence);
            (new \App\Services\GeneralService)->storeCache(key: \App\Enums\Cache\CacheKey::ProjectDealIdentifierNumber->value, value: $nextIdentifier, isForever: true);
        });

        static::deleted(function (ProjectDeal $projectDeal) {
            // identifier number will no be reset even when event has been deleted
        });
    }

    protected static function newFactory(): ProjectDealFactory
    {
        return ProjectDealFactory::new();
    }

    protected $appends = [
        'formatted_project_date',
        'status_text',
        'status_color',
    ];

    protected $casts = [
        'event_type' => \App\Enums\Production\EventType::class,
        'equipment_type' => \App\Enums\Production\EquipmentType::class,
        'status' => \App\Enums\Production\ProjectDealStatus::class,
    ];

    public function ledDetail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : null,
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function interactiveDetail(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : null,
            set: fn ($value) => $value ? json_encode($value) : null
        );
    }

    public function interactiveRequests(): HasMany
    {
        return $this->hasMany(InteractiveRequest::class, 'project_deal_id');
    }

    public function ProjectDealPriceChanges(): HasMany
    {
        return $this->hasMany(ProjectDealPriceChange::class, 'project_deal_id');
    }

    public function activeProjectDealPriceChange(): HasOne
    {
        return $this->hasOne(ProjectDealPriceChange::class, 'project_deal_id')
            ->where('status', ProjectDealChangePriceStatus::Pending);
    }

    public function marketings(): HasMany
    {
        return $this->hasMany(ProjectDealMarketing::class, 'project_deal_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class, 'project_deal_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'project_deal_id');
    }

    public function firstTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'project_deal_id')
            ->oldestOfMany();
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(\Modules\Company\Models\ProjectClass::class, 'project_class_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(ProjectQuotation::class, 'project_deal_id');
    }

    public function finalQuotation(): HasOne
    {
        return $this->hasOne(ProjectQuotation::class, 'project_deal_id')
            ->final();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'project_deal_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'project_deal_id');
    }

    public function getInvoice(string $invoiceUid)
    {
        return $this->invoice()
            ->with(['transaction'])
            ->where('uid', $invoiceUid)->first();
    }

    public function mainInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'project_deal_id')
            ->where('is_main', 1);
    }

    public function unpaidInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'project_deal_id')
            ->where('is_main', 0)
            ->where('status', \App\Enums\Transaction\InvoiceStatus::Unpaid);
    }

    public function unpaidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'project_deal_id')
            ->where('is_main', 0)
            ->where('status', \App\Enums\Transaction\InvoiceStatus::Unpaid);
    }

    public function latestQuotation(): HasOne
    {
        return $this->hasOne(ProjectQuotation::class, 'project_deal_id')
            ->latestOfMany();
    }

    public function customer(): BelongsTo
    {
        return $this->BelongsTo(\Modules\Production\Models\Customer::class, 'customer_id');
    }

    public function city(): BelongsTo
    {
        return $this->BelongsTo(\Modules\Company\Models\City::class, 'city_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(\Modules\Company\Models\Country::class, 'country_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(\Modules\Company\Models\State::class, 'state_id');
    }

    public function activeProjectDealChange(): HasOne
    {
        return $this->hasOne(ProjectDealChange::class, 'project_deal_id')
            ->where('status', ProjectDealChangeStatus::Pending);
    }

    public function formattedProjectDate(): Attribute
    {
        $output = null;

        if (isset($this->attributes['project_date'])) {
            $output = date('d F Y', strtotime($this->attributes['project_date']));
        }

        return Attribute::make(
            get: fn () => $output
        );
    }

    public function statusText(): Attribute
    {
        $output = __('global.undetermined');

        if (isset($this->attributes['status'])) {
            $statuses = \App\Enums\Production\ProjectStatus::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->attributes['status']) {
                    $output = $status->label();
                }
            }
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function statusColor(): Attribute
    {
        $output = 'grey-lighten-1';

        if (isset($this->attributes['status'])) {
            $statuses = \App\Enums\Production\ProjectStatus::cases();
            foreach ($statuses as $status) {
                if ($status->value == $this->attributes['status']) {
                    $output = $status->color();
                }
            }
        }

        return Attribute::make(
            get: fn () => $output,
        );
    }

    public function isDraft(): bool
    {
        return $this->attributes['status'] === \App\Enums\Production\ProjectDealStatus::Draft->value ? true : false;
    }

    public function isFinal(): bool
    {
        return $this->attributes['status'] === \App\Enums\Production\ProjectDealStatus::Final->value ? true : false;
    }

    public function isPaid(): bool
    {
        return isset($this->attributes['is_fully_paid']) ? (bool) $this->attributes['is_fully_paid'] : false;
    }

    // ###### CUSTOM FUNCTIONS
    /**
     * Get final price of project deals
     */
    public function getFinalPrice(bool $formatPrice = false): float|string
    {
        $output = 0;
        if (($this->relationLoaded('finalQuotation')) && ($this->finalQuotation)) {
            $output = $this->finalQuotation ? $this->finalQuotation->fix_price : 0;
        }

        return $formatPrice ? 'Rp'.number_format(num: $output, decimal_separator: ',') : floatval($output);
    }

    /**
     * Get price of latest quotations
     */
    public function getLatestPrice(bool $formatPrice = false): float|string
    {
        $output = 0;

        if ($this->relationLoaded('latestQuotation')) {
            $output = $this->latestQuotation->fix_price;
        }

        return $formatPrice ? 'Rp'.number_format(num: $output, decimal_separator: ',') : floatval($output);
    }

    /**
     * Get down payment amount
     */
    public function getDownPaymentAmount(bool $formatPrice = false): float|string
    {
        // load relation to transaction
        $output = 0;
        if ($this->relationLoaded('firstTransaction')) {
            $output = $this->firstTransaction ? $this->firstTransaction->payment_amount : 0;
        }

        return $formatPrice ? 'Rp'.number_format(num: $output, decimal_separator: ',') : floatval($output);
    }

    /**
     * Get amount of remaining payment
     */
    public function getRemainingPayment(bool $formatPrice = false, int $deductionAmount = 0): float|string
    {
        $output = 0;

        if ($this->relationLoaded('transactions') && isset($this->attributes['is_fully_paid']) && $this->getFinalPrice() > 0) {
            if (! $this->attributes['is_fully_paid']) {
                $output = $this->getFinalPrice() - $this->transactions->pluck('payment_amount')->sum();

                if ($deductionAmount > 0) {
                    $output = $output - $deductionAmount;
                }
            }
        }

        return $formatPrice ? 'Rp'.number_format(num: $output, decimal_separator: ',') : floatval($output);
    }

    /**
     * Get status of payment in each project deals
     */
    public function getStatusPayment(): string
    {
        $output = '-';

        if ($this->relationLoaded('transactions') && isset($this->attributes['is_fully_paid'])) {
            if ($this->attributes['is_fully_paid']) {
                $output = __('global.paid');
            } elseif (! $this->attributes['is_fully_paid'] && $this->transactions->count() > 0) {
                $output = __('global.partial');
            } elseif (! $this->attributes['is_fully_paid'] && $this->transactions->count() == 0) {
                $output = __('global.unpaid');
            }
        }

        return $output;
    }

    /**
     * Get color status of payment in each project deals
     */
    public function getStatusPaymentColor(): string
    {
        $output = 'blue-grey-lighten-4';

        if ($this->relationLoaded('transactions') && isset($this->attributes['is_fully_paid'])) {
            if ($this->attributes['is_fully_paid']) {
                $output = 'green-lighten-3';
            } elseif (! $this->attributes['is_fully_paid'] && $this->transactions->count() > 0) {
                $output = 'lime-darken-1';
            } elseif (! $this->attributes['is_fully_paid'] && $this->transactions->count() == 0) {
                $output = 'red-darken-1';
            }
        }

        return $output;
    }

    public function canMakePayment(): bool
    {
        // if project deal is fully paid, return false
        if ($this->is_fully_paid) {
            return false;
        }

        // if project deal has no final quotation, return false
        if (! $this->relationLoaded('finalQuotation') || ! $this->finalQuotation) {
            return false;
        }

        // if project deal has no final price, return false
        if ($this->getFinalPrice() <= 0) {
            return false;
        }

        // if project deal has remaining payment, return true
        if ($this->getRemainingPayment() > 0) {
            return true;
        }

        return false;
    }

    public function canPublishProject(): bool
    {
        return $this->isDraft();
    }

    public function canMakeFinal(): bool
    {
        $output = false;

        if ($this->relationLoaded('finalQuotation') && ! $this->finalQuotation && ! $this->isDraft() && ! $this->isFinal()) {
            // if project deal has latest quotation, return true
            $output = true;
        }

        return $output;
    }
}
