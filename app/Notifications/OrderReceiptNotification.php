<?php

namespace App\Notifications;

use App\Models\Agent;
use App\Models\Order;
use App\Models\Subagent;
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

        [$storeUrl, $trackUrl] = $this->storeLinks($o);
        $seller = $o->seller;

        return (new MailMessage)
            ->subject("Payment received — order {$o->reference}")
            ->markdown('mail.order-receipt', [
                'order' => $o,
                'bundle' => $bundle,
                'storeName' => $seller?->store_name ?: ($seller?->name ?? 'Your data store'),
                'storeUrl' => $storeUrl,
                'trackUrl' => $trackUrl,
            ]);
    }

    /**
     * Links back to the shop the customer bought from (D2 agent / D3 subagent domain) — never the
     * admin/agent domain, which storefront customers must not discover.
     *
     * @return array{0: string, 1: string}
     */
    private function storeLinks(Order $order): array
    {
        $seller = $order->seller;
        $track = ['by' => 'reference', 'reference' => $order->reference];

        return match (true) {
            $seller instanceof Subagent => [
                route('subagent.storefront', ['subagentSlug' => $seller->slug]),
                route('subagent.storefront.track', ['subagentSlug' => $seller->slug] + $track),
            ],
            $seller instanceof Agent => [
                route('agent.storefront', ['agentSlug' => $seller->slug]),
                route('agent.storefront.track', ['agentSlug' => $seller->slug] + $track),
            ],
            default => throw new \LogicException("Order {$order->reference} has no storefront seller."),
        };
    }
}
