<x-mail::message>
# Approval Required: Invoice Data Modification

Dear {{ $director->name }},

An invoice modification requires your approval:

<x-mail::panel>
**Invoice Number:** {{ $invoice->invoice->number }}<br>
**Client Name:** {{ $invoice->invoice->customer->name }}<br>
**Event Name:** {{ $invoice->invoice->projectDeal->name }}<br>
**Modified By:** {{ $actor->employee->name }}<br>
**Modification Date:** {{ now()->parse($invoice->created_at)->format('Y-m-d H:i') }}
</x-mail::panel>

**Changes Made:**
<x-mail::table>
| Field          | Old Value       | New Value       |
|:---------------:|:----------------:|:----------------:|
@foreach($changes as $field => $values)
| {{ ucfirst($field) }} | {{ $values['old'] }} | {{ $values['new'] }} |
@endforeach
</x-mail::table>
<table>
    <tr>
        <td>
            <x-mail::button :url="$approvalUrl" color="rgb(93, 135, 255)">
            Approve Changes
            </x-mail::button>
        </td>
        <td>
            <x-mail::button :url="$rejectionUrl" color="red">
            Reject Changes
            </x-mail::button>
        </td>
    </tr>
</table>

This request will expire in 24 hours. If you have any questions, please contact support.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>