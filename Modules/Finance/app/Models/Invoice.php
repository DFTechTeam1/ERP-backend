<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Finance\Database\Factories\InvoiceFactory;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;

// use Modules\Finance\Database\Factories\InvoiceFactory;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'amount',
        'paid_amount',
        'payment_date',
        'payment_due',
        'project_deal_id',
        'customer_id',
        'status',
        'raw_data',
        
        // numbering
        'parent_number',
        'number',
        'is_main',
        'sequence',

        'created_by'
    ];

    protected $casts = [
        'status' => \App\Enums\Transaction\InvoiceStatus::class
    ];

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    } 

    protected function rawData(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? json_decode($value, true) : [],
            set: fn($value) => $value ? json_encode($value) : null
        );
    }

    public function projectDeal(): BelongsTo
    {
        return $this->belongsTo(ProjectDeal::class, 'project_deal_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function getChilds(int $projectDealId, string $select = '*')
    {
        return Invoice::selectRaw($select)
            ->where('project_deal_id', $projectDealId)
            ->where('is_main', 0)
            ->get();
    }

    public function getLastInvoice(string $select = '*')
    {
        if (!$this->number) {
            return null;
        }

        
        return Invoice::selectRaw($select)
            ->where('project_deal_id', $this->project_deal_id)
            ->orderBy('sequence', 'desc')
            ->first();
    }
}
