<x-mail::message>

# New Interactive Project Request

Hello {{ $employee->name }},

There is a new interactive project request from {{ $request->requester->employee->name }} for project {{ $request->projectDeal->name }} dated {{ $request->projectDeal->project_date }}.

Please review and take the necessary actions.

Interactive Details:
<x-mail::panel>
**Project Name:** {{ $request->projectDeal->name }}<br>
**Project Date:** {{ date('d F Y', strtotime($request->projectDeal->project_date)) }}<br>
**Requested By:** {{ $request->requester->employee->name }}<br>
**Request Date:** {{ now()->parse($request->requested_at)->format('Y-m-d H:i') }} <br>
**Total Interactive LED**: {{ $request->interactive_area }}<br>
**Interactive Fee**: Rp {{ number_format($request->interactive_fee, 2) }}<br>
**Current Fix Price**: Rp {{ number_format($request->projectDeal->latestQuotation->fix_price, 2) }}<br>
**New Fix Price**: Rp {{ number_format($request->fix_price, 2) }}<br>
</x-mail::panel>

{{-- button approval and reject --}}
{{-- display in flex --}}
<div style="display: flex; gap: 12px;">
    <x-mail::button :url="url('/production/interactive-requests/'.$request->id)" color="rgb(93, 135, 255)">
    Approve
    </x-mail::button>
    <x-mail::button :url="url('/production/interactive-requests/'.$request->id)" color="red">
    Reject
    </x-mail::button>
</div>

Thank you.

</x-mail::message>