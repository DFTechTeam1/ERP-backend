<x-mail::message>
# Welcome to {{ config('app.name') }}!

Dear {{ $employeeName }},

You have been invited to access the **{{ config('app.name') }} ERP System**. Please find your login credentials below.

<x-mail::panel>
**Your Login Credentials**

| Field    | Details          |
|----------|------------------|
| URL      | {{ $erpUrl }}    |
| Email    | {{ $email }}     |
| Password | {{ $password }}  |
</x-mail::panel>

**Important:** For security reasons, please change your password immediately after your first login.

Before you can access the system, you need to activate your account by confirming your email address. Click the button below to activate:

<x-mail::button :url="$activationUrl">
Activate My Account
</x-mail::button>

If the button above does not work, copy and paste the following link into your browser:

{{ $activationUrl }}

> This activation link will expire in **24 hours**. If you did not expect this invitation, please ignore this email or contact HR immediately.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
