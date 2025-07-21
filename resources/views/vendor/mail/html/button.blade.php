@props([
    'url',
    'color' => 'rgb(0,0,0)',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; text-align:center;">
<tr>
<td>
<a href="{{ $url }}" class="button" style="border: none; padding: 8px 12px; color: #fff; background-color: {{$color}}; font-size: 16px; width: 100%; border-radius: 4px;" target="_blank" rel="noopener">{{ $slot }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
