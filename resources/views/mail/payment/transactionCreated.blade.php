<x-mail::message>
@if($transaction->transaction_type->value === 'down_payment')
# Down Payment Received 🟢
@elseif($transaction->transaction_type->value === 'credit')
# Credit Payment Processed 🟡
@elseif($transaction->transaction_type->value === 'repayment')
# Loan Repayment Received 🔵
@else
# New Transaction Processed
@endif

Dear Finance Team,

@if($transaction->transaction_type->value === 'down_payment')
A **down payment** of **{{ number_format($transaction->payment_amount, 2, ',') }} IDR** has been received for order **{{ $transaction->reference }}**.
@elseif($transaction->transaction_type->value === 'credit')
A **credit purchase** has been authorized with **{{ number_format($transaction->payment_amount, 2, ',') }} IDR** due by {{ now()->parse($transaction->invoice->payment_due)->format('d F Y') }}.
@elseif($transaction->transaction_type->value === 'repayment')
A **repayment** of **{{ number_format($transaction->payment_amount, 2, ',') }} IDR** has been received against credit note.
@endif

**Transaction Details:**  
- **Type:** <span style="color: 
    @if($transaction->transaction_type->value === 'down_payment') #2ecc71
    @elseif($transaction->transaction_type->value === 'credit') #f39c12
    @else #3498db
    @endif">
    {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type->value)) }}
  </span>  
- **ID:** {{ $transaction->trx_id }}  
- **Date:** {{ $transaction->created_at->format('d F Y H:i') }}  
- **Amount:** {{ number_format($transaction->payment_amount, 2, ',') }} IDR  
- **Customer:** {{ $transaction->customer->name }}  

@if($transaction->transaction_type->value === 'down_payment')
**Order Balance:** {{ number_format($remainingBalance, 2, ',') }} IDR  
@elseif($transaction->transaction_type->value === 'credit')
{{-- **Credit Terms:** {{ $transaction->payment_terms }} days   --}}
**Payment Date:** {{ $transaction->created_at->format('d F Y') }}  
@endif

<x-mail::panel>
**Action Required:**  
@if($transaction->transaction_type->value === 'down_payment')
1. Confirm order fulfillment status  
2. Update accounts receivable  
@elseif($transaction->transaction_type->value === 'credit')
1. Verify credit approval  
2. Schedule follow-up reminder  
@else
1. Reconcile with credit note  
2. Update customer credit limit  
@endif
</x-mail::panel>

<x-mail::button :url="$invoiceUrl">
View Invoice
</x-mail::button>

Regards,  
{{ config('app.name') }}
</x-mail::message>