<x-mail::message>
# Email Activation

<p>@lang('global.userHasBeenInvited', ['email' => $user->email])</p>

<div style="overflow-wrap: break-word;">
    @lang('global.activateGuide', ['url' => $urlActivate])
</div>

<x-mail::button url="https://google.com" style="border: none; padding: 8px 12px; color: #fff; background-color: #000; font-size: 16px; width: 100%; border-radius: 4px;">
    @lang('global.activate')
</x-mail::button>

</x-mail::message>