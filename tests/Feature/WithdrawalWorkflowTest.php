<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Withdrawal;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function withdrawal(string $status = Withdrawal::STATUS_PENDING): Withdrawal
    {
        $agent = Agent::create(['name' => 'Ama', 'phone' => '0551110000', 'password' => 'password', 'is_active' => true]);

        return $agent->withdrawals()->create(['amount' => 50, 'status' => $status]);
    }

    public function test_pending_can_be_approved_then_paid(): void
    {
        $service = new WithdrawalService;
        $w = $this->withdrawal();

        $this->assertTrue($service->transition($w, Withdrawal::STATUS_APPROVED));
        $this->assertSame(Withdrawal::STATUS_APPROVED, $w->status);

        $this->assertTrue($service->transition($w, Withdrawal::STATUS_PAID));
        $this->assertSame(Withdrawal::STATUS_PAID, $w->status);
        $this->assertNotNull($w->processed_at);
    }

    public function test_cannot_skip_pending_to_paid(): void
    {
        $service = new WithdrawalService;
        $w = $this->withdrawal();

        $this->assertFalse($service->transition($w, Withdrawal::STATUS_PAID));
        $this->assertSame(Withdrawal::STATUS_PENDING, $w->fresh()->status);
    }

    public function test_pending_can_be_rejected(): void
    {
        $service = new WithdrawalService;
        $w = $this->withdrawal();

        $this->assertTrue($service->transition($w, Withdrawal::STATUS_REJECTED, 'Bad account details'));
        $this->assertSame(Withdrawal::STATUS_REJECTED, $w->status);
        $this->assertSame('Bad account details', $w->admin_notes);
    }

    public function test_paid_is_terminal(): void
    {
        $service = new WithdrawalService;
        $w = $this->withdrawal(Withdrawal::STATUS_PAID);

        $this->assertFalse($service->transition($w, Withdrawal::STATUS_REJECTED));
    }
}
