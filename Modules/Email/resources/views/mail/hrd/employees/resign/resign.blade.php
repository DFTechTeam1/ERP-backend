<x-mail::message>
# Resignation Schedule Confirmation

Dear {{ $employeeName }},

We would like to inform you that your resignation has been officially scheduled. Please review the details below.

<x-mail::table>
| Details            | Information              |
|--------------------|--------------------------|
| Employee Name      | {{ $employeeName }}      |
| Employee ID        | {{ $employeeId }}        |
| Position           | {{ $position }}          |
| Department         | {{ $department }}        |
| Resign Date        | {{ $resignDate }}        |
</x-mail::table>

**Important Notice:**

Effective on your resign date (**{{ $resignDate }}**), the following will occur automatically:

<x-mail::panel>
All system accounts and access associated with your employee profile will be **permanently deactivated**, including:

- ERP system access
- Company email account
- Internal applications and tools
- Any other company-related digital accounts

Please ensure you have completed all necessary handover tasks and retrieved any personal data before this date.
</x-mail::panel>

If you have any questions or concerns regarding this schedule, please contact the HR department as soon as possible.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
