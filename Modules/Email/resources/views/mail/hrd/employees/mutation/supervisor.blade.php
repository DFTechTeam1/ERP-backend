<x-mail::message>
# Team Member Mutation Notice

Dear {{ $supervisorName }},

Please be informed that the following team member has been reassigned (mutation):

<x-mail::table>
| Details            | Information        |
|--------------------|--------------------|
| Employee Name      | {{ $employeeName }} |
| Previous Position  | {{ $oldPosition }} |
| New Position       | {{ $newPosition }} |
| Department         | {{ $department }}  |
| Effective Date     | {{ $effectiveDate }} |
</x-mail::table>

Kindly ensure a smooth transition and provide necessary support to the employee.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>