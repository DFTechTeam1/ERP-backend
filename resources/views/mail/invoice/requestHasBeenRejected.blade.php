<x-mail::message>
# Invoice Rejected

Hi {{ $invoice->user->employee->name }},

We’re sorry to inform you that your invoice request **#{{ $invoice->invoice->parent_number }}** has been **rejected**.

**📄 Invoice Details**

- **Invoice Number:** {{ $invoice->invoice->parent_number }}
@if ($invoice->amount)
- **Amount:** Rp{{ number_format($invoice->amount, 2) }}
@endif
@if ($invoice->payment_date)
- **Payment Date:** {{ now()->parse($invoice->payment_date)->format('d F Y') }}
@endif
- **Rejection Date:** {{ now()->parse($invoice->rejected_at)->format('d F Y H:i') }}

If you have any questions or need further assistance, feel free to reach out.

Thanks,<br>
{{ config('app.name') }}

</x-mail::message>
