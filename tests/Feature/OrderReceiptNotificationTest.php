<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DbhConfig;
use App\Models\Order;
use App\Notifications\OrderReceiptNotification;
use App\Services\Orders\NewOrderData;
use App\Services\Orders\OrderDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderReceiptNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);
        Queue::fake();
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 5, 'orderStatus' => 'processing'],
        ])]);
    }

    private function awaitingOrder(?string $email)
    {
        $agent = Agent::create(['name' => 'Agent', 'phone' => '0551110000', 'password' => 'secret', 'is_active' => true]);

        return app(OrderDispatchService::class)->createStorefrontAwaiting(new NewOrderData(
            seller: $agent, network: 'mtn', capacityGb: 5, beneficiaryPhone: '0559999999',
            customerPrice: 30, sellerCost: 20, agentCost: 20, baseCost: 15,
            channel: Order::CHANNEL_ONLINE, source: Order::SOURCE_STOREFRONT,
            customerEmail: $email,
        ));
    }

    public function test_receipt_is_emailed_to_the_customer_when_payment_is_confirmed(): void
    {
        Notification::fake();
        $order = $this->awaitingOrder('buyer@example.com');

        app(OrderDispatchService::class)->fulfillPaid($order);

        Notification::assertSentOnDemand(
            OrderReceiptNotification::class,
            fn (OrderReceiptNotification $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'buyer@example.com',
        );
    }

    public function test_no_email_is_sent_when_no_address_was_provided(): void
    {
        Notification::fake();
        $order = $this->awaitingOrder(null);

        app(OrderDispatchService::class)->fulfillPaid($order);

        Notification::assertNothingSent();
    }
}
