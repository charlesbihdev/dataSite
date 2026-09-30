<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BaseCost;
use App\Models\DbhConfig;
use App\Models\Order;
use App\Models\PricingTier;
use App\Models\TierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaceOrderCartTest extends TestCase
{
    use RefreshDatabase;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        DbhConfig::create(['base_url' => 'https://dbh.test/api', 'api_key' => 'k', 'is_active' => true]);

        $tier = PricingTier::create(['name' => 'Standard', 'is_active' => true]);
        TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'mtn',
            'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 5.0, 'is_active' => true,
        ]);
        BaseCost::create(['network' => 'mtn', 'min_gb' => 1, 'max_gb' => 100, 'cost_per_gb' => 3.0, 'is_active' => true]);
        TierPrice::create([
            'pricing_tier_id' => $tier->id, 'network' => 'at',
            'min_gb' => 1, 'max_gb' => 100, 'price_per_gb' => 4.0, 'is_active' => true,
        ]);
        BaseCost::create(['network' => 'at', 'min_gb' => 1, 'max_gb' => 100, 'cost_per_gb' => 2.0, 'is_active' => true]);

        $this->agent = Agent::factory()->create(['pricing_tier_id' => $tier->id]);
        $this->agent->walletOrCreate()->credit(100, 'topup');
        $this->actingAs($this->agent, 'agent');
    }

    private function fakeUpstreamCompleted(): void
    {
        Http::fake(['dbh.test/api/create_order' => Http::response([
            'success' => true, 'data' => ['requestId' => 1, 'orderStatus' => 'completed', 'price' => 15.0],
        ])]);
    }

    public function test_adding_a_bundle_prices_and_stores_a_cart_line(): void
    {
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5])
            ->assertRedirect(route('agent.dashboard'));

        $this->get(route('agent.dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->has('cart', 1)
                ->where('cart.0.network', 'mtn')
                ->where('cart.0.cost', 25) // 5 GB * 5.00/GB
                ->where('cartTotal', 25)
        );
    }

    public function test_invalid_phone_is_rejected_and_nothing_is_added(): void
    {
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '12345', 'bundle_size' => 5])
            ->assertSessionHasErrors('beneficiary_phone');

        $this->assertEmpty(session('agent_cart', []));
    }

    public function test_bulk_paste_adds_valid_lines_and_skips_the_rest(): void
    {
        $this->post(route('agent.cart.bulk'), [
            'bulk_orders_text' => "0559999999 5\ngarbage line\n0244000000 10",
        ])->assertRedirect(route('agent.dashboard'));

        $this->assertCount(2, session('agent_cart'));
    }

    public function test_upload_honors_an_explicit_network_column_over_prefix_detection(): void
    {
        // 055 is an MTN prefix, but column C forces AT — the line should price and store as AT.
        $csv = "Receiver,Capacity (GB),Network\n0559999999,5,AT\n0244000000,10,\n";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $this->post(route('agent.cart.upload'), ['orders_file' => $file])
            ->assertRedirect(route('agent.dashboard'));

        $cart = collect(session('agent_cart'));
        $this->assertCount(2, $cart);
        $this->assertSame('at', $cart->firstWhere('beneficiary_phone', '0559999999')['network']);
        $this->assertSame(20.0, $cart->firstWhere('beneficiary_phone', '0559999999')['cost']); // 5 GB * 4.00 (AT)
        $this->assertSame('mtn', $cart->firstWhere('beneficiary_phone', '0244000000')['network']); // blank → auto-detect
    }

    public function test_upload_falls_back_to_detection_for_an_unrecognized_network(): void
    {
        // "Telcel" is a typo → not a recognized code, so we auto-detect from the phone (055 = MTN).
        $csv = "Receiver,Capacity (GB),Network\n0559999999,5,Telcel\n";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $this->post(route('agent.cart.upload'), ['orders_file' => $file])
            ->assertRedirect(route('agent.dashboard'));

        $cart = collect(session('agent_cart'));
        $this->assertCount(1, $cart);
        $this->assertSame('mtn', $cart->first()['network']);
    }

    public function test_a_full_admin_export_csv_can_be_re_uploaded(): void
    {
        // Columns are located by header name, so the export's leading Reference/Seller/Type are ignored.
        $csv = "Reference,Seller,Type,Receiver,Capacity (GB),Network,Customer Price,Seller Cost,Status,Source,Payment,Upstream Ref,Created\n"
            ."DS-0001,Kwame,Agent,0559999999,5,MTN,25,15,completed,portal,paid,,2026-09-30 10:00:00\n"
            ."DS-0002,Kwame,Agent,0244000000,10,MTN,50,30,completed,portal,paid,,2026-09-30 10:05:00\n";
        $file = UploadedFile::fake()->createWithContent('export.csv', $csv);

        $this->post(route('agent.cart.upload'), ['orders_file' => $file])
            ->assertRedirect(route('agent.dashboard'));

        $cart = collect(session('agent_cart'));
        $this->assertCount(2, $cart);
        $this->assertSame('0559999999', $cart->first()['beneficiary_phone']);
        $this->assertSame('mtn', $cart->first()['network']);
        $this->assertSame(5, $cart->first()['capacity_gb']);
    }

    public function test_upload_handles_the_excel_text_literal_phone_the_export_writes(): void
    {
        // The admin export writes the phone as ="0559999999" (Excel text-literal); it must still parse.
        $csv = "Reference,Seller,Type,Receiver,Capacity (GB),Network\nDS-1,Kwame,Agent,\"=\"\"0559999999\"\"\",5,MTN\n";
        $file = UploadedFile::fake()->createWithContent('export.csv', $csv);

        $this->post(route('agent.cart.upload'), ['orders_file' => $file])
            ->assertRedirect(route('agent.dashboard'));

        $cart = collect(session('agent_cart'));
        $this->assertCount(1, $cart);
        $this->assertSame('0559999999', $cart->first()['beneficiary_phone']);
    }

    public function test_upload_recovers_a_phone_that_lost_its_leading_zero(): void
    {
        // Excel drops the leading 0 (0551234567 → 551234567); normalize() restores it on upload.
        $csv = "Receiver,Capacity (GB),Network\n551234567,5,\n";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $this->post(route('agent.cart.upload'), ['orders_file' => $file])
            ->assertRedirect(route('agent.dashboard'));

        $cart = collect(session('agent_cart'));
        $this->assertCount(1, $cart);
        $this->assertSame('0551234567', $cart->first()['beneficiary_phone']);
    }

    public function test_template_is_excel_with_the_phone_column_kept_as_text(): void
    {
        $response = $this->get(route('agent.cart.template'));
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();

        $this->assertSame('Receiver', $sheet->getCell('A1')->getValue());
        $this->assertSame('Capacity (GB)', $sheet->getCell('B1')->getValue());
        $this->assertSame('Network', $sheet->getCell('C1')->getValue());
        $this->assertSame('0551234567', (string) $sheet->getCell('A2')->getValue()); // leading 0 preserved
        @unlink($tmp);
    }

    public function test_checkout_places_orders_and_debits_the_wallet(): void
    {
        $this->fakeUpstreamCompleted();
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5]);

        $this->post(route('agent.cart.checkout'))->assertRedirect(route('agent.dashboard'));

        $order = Order::sole();
        $this->assertSame('portal', $order->source);
        $this->assertSame('0559999999', $order->beneficiary_phone);
        $this->assertSame(75.0, (float) $this->agent->walletOrCreate()->balance); // 100 - 25
        $this->assertEmpty(session('agent_cart', []));
    }

    public function test_checkout_rejects_when_balance_is_short_and_keeps_the_cart(): void
    {
        $this->agent->walletOrCreate()->debit(90, 'spend'); // leaves 10, order costs 25
        $this->post(route('agent.cart.store'), ['beneficiary_phone' => '0559999999', 'bundle_size' => 5]);

        $this->post(route('agent.cart.checkout'))->assertRedirect(route('agent.dashboard'));

        $this->assertSame(0, Order::count());
        $this->assertCount(1, session('agent_cart'));
        $this->assertSame(10.0, (float) $this->agent->walletOrCreate()->balance);
    }
}
