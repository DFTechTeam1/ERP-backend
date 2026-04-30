<x-mail::message>
# Employee Mutation Notification

Dear {{ $employeeName }},

We would like to inform you that your job assignment has been updated (mutation).

<x-mail::table>
| Details            | Information        |
|--------------------|--------------------|
| Previous Position  | {{ $oldPosition }} |
| New Position       | {{ $newPosition }} |
| Department         | {{ $department }}  |
| Effective Date     | {{ $effectiveDate }} |
</x-mail::table>

Please contact HR if you have any questions regarding this change.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>