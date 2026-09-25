<?php

namespace App\Enums\Production\Deal;

enum DealLogAction: string
{
    case Created = 'created';
    case DealUpdated = 'deal_updated';
    case QuotationAdded = 'quotation_added';
    case PriceChangeRequested = 'price_change_requested';
    case PriceChangeApproved = 'price_change_approved';
    case StatusChanged = 'status_changed';
    case PaymentRecorded = 'payment_recorded';
    case InteractiveAdded = 'interactive_added';
    case Refund = 'refund';
    case Cancelled = 'cancelled';
    case Final = 'final';
}
