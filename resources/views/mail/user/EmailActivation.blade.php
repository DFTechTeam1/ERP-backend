<x-mail::message>
# Email Activation

<p>@lang('global.userHasBeenInvited', ['email' => $user->email])</p>

<div style="overflow-wrap: break-word;">
    @lang('global.activateGuide', ['url' => $urlActivate])
</div>

<table style="width: 400px; margin-top: 20px;">
    <tbody>
        <tr>
            <td style="width: 30%; font-weight: bold;">@lang('global.email')</td>
            <td style="width: 10%;">:</td>
            <td style="width: 40%;">{{ $user->email }}</td>
        </tr>
        <tr>
            <td style="width: 30%; font-weight: bold;">@lang('global.password')</td>
            <td style="width: 10%;">:</td>
            <td style="width: 40%;">{{ $password }}</td>
        </tr>
    </tbody>
</table>

<x-mail::button url="{{ $urlActivate }}" style="border: none; padding: 8px 12px; color: #fff; background-color: #000; font-size: 16px; width: 100%; border-radius: 4px;">
    @lang('global.activate')
</x-mail::button>

</x-mail::message>