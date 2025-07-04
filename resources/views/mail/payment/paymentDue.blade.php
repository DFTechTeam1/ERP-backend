<x-mail::message>
# Payment Due Reminder

Dear Marketing Team,

This is a reminder that **{{ $projectDeal->name }}'s** payment of **{{ number_format(num: $projectDeal->finalQuotation->fix_price, decimal_separator: ',') }}** is due on **{{ date('d F Y', strtotime($projectDeal->project_date)) }}**.

**Client Details:**  
- **Client Name:** {{ $projectDeal->customer->name }} 
- **Invoice #:** {{ $invoiceNumber }}
- **Amount Due:** {{ $remainingPayment }}
- **Due Date:** {{ date('d F Y', strtotime($projectDeal->project_date)) }}

**Action Required:**  
1. Follow up with the client via email or call  
2. Update the finance team once payment is confirmed  

<x-mail::button :url="$url">
View Invoice
</x-mail::button>

Please let us know if there are any payment issues or delays.

Thanks,  
{{ config('app.name') }}
</x-mail::message>