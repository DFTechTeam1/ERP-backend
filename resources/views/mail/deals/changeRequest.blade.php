<x-mail::message>
# Approval Required: Final Event Modification

Dear {{ $director->name }},

An event modification requires your approval:

<x-mail::panel>
**Event Name:** {{ $data->projectDeal->name }}<br>
**Event Date:** {{ date('d F Y', strtotime($data->projectDeal->project_date)) }}<br>
**Modified By:** {{ $data->requester->employee->nickname }}<br>
**Modification Date:** {{ now()->parse($data->requested_at)->format('Y-m-d H:i') }}
</x-mail::panel>

**Changes Made:**
<table style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="border: 1px solid #e6e6e6;">Field</th>
            <th style="border: 1px solid #e6e6e6;">Old Value</th>
            <th style="border: 1px solid #e6e6e6;">New Value</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data->detail_changes as $values)
            @if (gettype($values['new_value']) == 'string')
                <tr>
                    <td style="border: 1px solid #e6e6e6; padding: 4px 8px;">{{ $values['label'] }}</td>
                    <td style="border: 1px solid #e6e6e6; padding: 4px 8px;">{!! $values['old_value'] !!}</td>
                    <td style="border: 1px solid #e6e6e6; padding: 4px 8px;">{!! $values['new_value'] !!}</td>
                </tr>
            @elseif(gettype($values['new_value']) == 'array')
                <tr>
                    <td style="border: 1px solid #e6e6e6; padding: 4px 8px;">{{ $values['label'] }}</td>
                    <td style="border: 1px solid #e6e6e6; padding: 4px 8px;">
                        @foreach ($values['old_value'] as $keyOld => $oldValue)
                            <div @if(count($values['old_value']) > 1 && $keyOld != count($values['old_value']) - 1) style="border-bottom: 1px solid #e6e6e6; margin-top: 10px; margin-bottom: 10px;" @endif>
                                <p style="margin-bottom: 0;">Detail: {{ $oldValue['textDetail'] }}</p>
                                <p style="margin-bottom: 0;">Total: {!! $oldValue['total'] !!}</p>
                            </div>
                        @endforeach
                    </td>
                    <td style="border: 1px solid #e6e6e6; padding: 4px 8px;">
                        @foreach ($values['new_value'] as $newValue)
                            <div @if(count($values['new_value']) > 1 && $keyOld != count($values['old_value']) - 1) style="border-bottom: 1px solid #e6e6e6; margin-top: 10px; margin-bottom: 10px;" @endif>
                                <p style="margin-bottom: 0;">Detail: {{ $newValue['textDetail'] }}</p>
                                <p style="margin-bottom: 0;">Total: {!! $newValue['total'] !!}</p>
                            </div>
                        @endforeach
                    </td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
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

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>