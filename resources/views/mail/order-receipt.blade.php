{{-- Own layout, not mail::message: that one brands the email with APP_NAME/APP_URL, which would
     expose the admin/agent domain to storefront customers. Everything here points at the shop. --}}
<x-mail::layout :title="$storeName">
<x-slot:header>
<x-mail::header :url="$storeUrl">
{{ $storeName }}
</x-mail::header>
</x-slot:header>

# Thank you for your purchase!

We've received your payment and your data bundle is on its way.

<x-mail::table>
| | |
| :-- | --: |
| Reference | **{{ $order->reference }}** |
| Bundle | {{ $bundle }} |
| Recipient | {{ $order->beneficiary_phone }} |
| Amount | GHS {{ number_format((float) $order->customer_price, 2) }} |
| Date | {{ $order->created_at?->format('M j, Y g:i A') ?? '—' }} |
</x-mail::table>

<x-mail::button :url="$trackUrl">
Track your order
</x-mail::button>

Keep this reference number to track your order.

Thanks,<br>
{{ $storeName }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $storeName }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
