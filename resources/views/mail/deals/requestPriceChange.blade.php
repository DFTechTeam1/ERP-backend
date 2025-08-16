{{-- resources/views/emails/project-price-change-request.blade.php --}}
<x-mail::message>
# Hello {{ $director->name }}

A request to change the price for the project "{{ $project->name }}" has been submitted.

Please review the request and take the necessary actions.

<table>
    <tr style='width: 100%;'>
        <td>
            <x-mail::button :url="$rejectionUrl" color="red">
            Reject
            </x-mail::button>
        </td>
        <td>
            <x-mail::button :url="$approvalUrl">
            Approve
            </x-mail::button>
        </td>
    </tr>
</table>


Thank you for your attention to this matter.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>