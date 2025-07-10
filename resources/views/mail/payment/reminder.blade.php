<style>
.table-invoice {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.table-invoice thead tr th {
    padding: 8px 12px;
    text-align: center;
    border: 1px solid #e6e6e6;
    font-size: 13px;
}

.table-invoice tbody tr td {
    padding: 8px 12px;
    text-align: center;
    border: 1px solid #e6e6e6;
    font-size: 12px;
}
</style>

@component('mail::message')
# 🔔 Payment Due Reminder

Dear {{ $user->employee ? $user->employee->name : $user->email }},

This is a reminder that the following customer invoice{{ count($invoices) > 1 ? 's are' : ' is' }} due soon:

<table class="table-invoice">
    <thead>
        <tr>
            <th>Invoice</th>
            <th>Customer</th>
            <th>Amount</th>
            <th>Due Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->number }}</td>
                <td>{{ $invoice->customer->name }}</td>
                <td>Rp{{ number_format(num: $invoice->amount, decimal_separator: ',') }}</td>
                <td>{{ date('d F Y', strtotime($invoice->payment_due)) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

Please take the necessary action to ensure these payments are followed up on time.

Thanks,  
{{ config('app.name') }}
@endcomponent
