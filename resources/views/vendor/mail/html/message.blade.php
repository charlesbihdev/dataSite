{{-- Framework message layout, branded with the sender name (not APP_NAME/APP_URL) so no email exposes the admin/agent domain. --}}
<x-mail::layout :title="config('mail.from.name')">
{{-- Header --}}
<x-slot:header>
<x-mail::header>
{{ config('mail.from.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('mail.from.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
