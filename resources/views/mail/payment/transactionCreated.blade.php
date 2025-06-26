<x-mail::message>
@if($transaction->type === 'down_payment')
# Down Payment Received 🟢
@elseif($transaction->type === 'credit')
# Credit Payment Processed 🟡
@elseif($transaction->type === 'repayment')
# Loan Repayment Received 🔵
@else
# New Transaction Processed
@endif

Dear Finance Team,

@if($transaction->type === 'down_payment')
A **down payment** of **{{ number_format($transaction->amount, 2, ',') }} {{ $transaction->currency }}** has been received for order **{{ $transaction->order->reference }}**.
@elseif($transaction->type === 'credit')
A **credit purchase** has been authorized with **{{ number_format($transaction->amount, 2, ',') }} {{ $transaction->currency }}** due by {{ $transaction->due_date->format('d F Y') }}.
@elseif($transaction->type === 'repayment')
A **repayment** of **{{ number_format($transaction->amount, 2, ',') }} {{ $transaction->currency }}** has been received against credit note **{{ $transaction->credit_note_number }}**.
@endif

**Transaction Details:**  
- **Type:** <span style="color: 
    @if($transaction->type === 'down_payment') #2ecc71
    @elseif($transaction->type === 'credit') #f39c12
    @else #3498db
    @endif">
    {{ ucfirst(str_replace('_', ' ', $transaction->type)) }}
  </span>  
- **ID:** {{ $transaction->trx_id }}  
- **Date:** {{ $transaction->created_at->format('d F Y H:i') }}  
- **Amount:** {{ number_format($transaction->amount, 2, ',') }} {{ $transaction->currency }}  
- **Customer:** {{ $transaction->customer->name }}  

@if($transaction->type === 'down_payment')
**Order Balance:** {{ number_format($transaction->order->remaining_balance, 2, ',') }} {{ $transaction->currency }}  
@elseif($transaction->type === 'credit')
**Credit Terms:** {{ $transaction->payment_terms }} days  
**Next Payment Due:** {{ $transaction->due_date->addDays($transaction->payment_terms)->format('d F Y') }}  
@elseif($transaction->type === 'repayment')
**Remaining Credit:** {{ number_format($transaction->credit->remaining_amount, 2, ',') }} {{ $transaction->currency }}  
@endif

<x-mail::panel>
**Action Required:**  
@if($transaction->type === 'down_payment')
1. Confirm order fulfillment status  
2. Update accounts receivable  
@elseif($transaction->type === 'credit')
1. Verify credit approval  
2. Schedule follow-up reminder  
@else
1. Reconcile with credit note  
2. Update customer credit limit  
@endif
</x-mail::panel>

<x-mail::button :url="$invoiceUrl">
View {{ $transaction->type === 'credit' ? 'Credit Agreement' : 'Invoice' }}
</x-mail::button>

Regards,  
{{ config('app.name') }}
</x-mail::message>