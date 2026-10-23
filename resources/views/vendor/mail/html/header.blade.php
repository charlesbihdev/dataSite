{{-- Framework header with an optional link: shared emails show the sender name unlinked. --}}
@props(['url' => null])
<tr>
<td class="header">
@if ($url)
<a href="{{ $url }}" style="display: inline-block;">{!! $slot !!}</a>
@else
<span style="display: inline-block;">{!! $slot !!}</span>
@endif
</td>
</tr>
