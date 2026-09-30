<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to the storefront customer once their payment is confirmed. Carries the order details and
 * reference number so they can track the purchase. Queued so it never blocks checkout — on shared
 * hosting the queue is drained by the scheduled `queue:work --stop-when-empty` (no persistent worker).
 */
class OrderReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;
        $bundle = strtoupper($o->network).' '.rtrim(rtrim(number_format((float) $o->capacity_gb, 2), '0'), '.').'GB';

        return (new MailMessage)
            ->subject("Payment received — order {$o->reference}")
            ->greeting('Thank you for your purchase!')
            ->line("We've received your payment and your data bundle is on its way.")
            ->line("Reference: {$o->reference}")
            ->line("Bundle: {$bundle}")
            ->line("Recipient: {$o->beneficiary_phone}")
            ->line('Amount: GHS '.number_format((float) $o->customer_price, 2))
            ->line('Date: '.($o->created_at?->format('M j, Y g:i A') ?? '—'))
            ->line('Keep this reference number to track your order.');
    }
}
