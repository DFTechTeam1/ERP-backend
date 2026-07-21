<x-mail::message>
# @lang('global.otpSignDocumentHeading')

<p>@lang('global.otpSignDocumentIntro')</p>

<x-mail::panel>
<div style="text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 12px;">
    {{ $otp }}
</div>
</x-mail::panel>

<p>@lang('global.otpSignDocumentExpiry', ['minutes' => $expiresInMinutes])</p>

<p>@lang('global.otpSignDocumentIgnore')</p>
</x-mail::message>
