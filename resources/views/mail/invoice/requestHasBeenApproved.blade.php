<x-mail::message>
# Invoice Approved

Hi {{ $invoice->user->employee->name }},

We’re pleased to inform you that your invoice request **#{{ $invoice->invoice->parent_number }}** has been **approved**.

**📄 Invoice Details**

- **Invoice Number:** {{ $invoice->invoice->parent_number }}
@if ($invoice->amount)
- **Amount:** Rp{{ number_format($invoice->amount, 2) }}
@endif
@if ($invoice->payment_date)
- **Payment Date:** {{ now()->parse($invoice->payment_date)->format('d F Y') }}
@endif
- **Approval Date:** {{ now()->parse($invoice->approved_at)->format('d F Y H:i') }}

<x-mail::button :url="$invoiceUrl">
View Invoice
</x-mail::button>

If you have any questions or need further assistance, feel free to reach out.

Thanks,<br>
{{ config('app.name') }}

</x-mail::message>
