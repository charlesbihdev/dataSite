<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PollUpstreamOrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderBulkService;
use App\Services\Orders\OrderDispatchService;
use App\Services\Orders\OrderListPresenter;
use App\Services\Orders\OrderSettlementService;
use App\Services\Payments\PaymentVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Superadmin order backoffice. One table, two pages: AGENT orders (seller-placed, portal + API)
 * and REGULAR orders (public storefront). Each page gets the actions its workflow needs — agent:
 * poll + refund; regular: verify payment, mark verified, delete. Listing is built by the presenter.
 */
class OrdersController extends Controller
{
    public function agent(Request $request, OrderListPresenter $presenter): Response
    {
        return Inertia::render('admin/orders/agent', $presenter->listing($request, OrderListPresenter::SEGMENT_AGENT));
    }

    public function regular(Request $request, OrderListPresenter $presenter): Response
    {
        return Inertia::render('admin/orders/regular', $presenter->listing($request, OrderListPresenter::SEGMENT_REGULAR));
    }

    /**
     * Re-queue a status poll for a processing order (Databundleshub is poll-only).
     */
    public function poll(Order $order): RedirectResponse
    {
        if ($order->status === Order::STATUS_PROCESSING && $order->upstream_request_id !== null) {
            PollUpstreamOrderStatus::dispatch($order->id);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Status poll queued for {$order->reference}."]);
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only processing orders with an upstream reference can be polled.']);
        }

        return back();
    }

    /**
     * Verify a storefront order's payment against the gateway and act on the real result — the
     * admin never decides the outcome. PAID → dispatch the bundle (record earnings → processing →
     * push to Databundleshub); FAILED → mark the payment failed, nothing dispatched; PENDING →
     * leave it awaiting so a later check can resolve it. Combining verify + dispatch is our fit:
     * unlike DBH, our only fulfillment is the automated upstream call.
     */
    public function verifyPayment(Order $order, PaymentVerifier $verifier, OrderDispatchService $dispatch): RedirectResponse
    {
        if ($order->payment_status !== Order::PAYMENT_AWAITING) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only orders awaiting payment can be verified.']);

            return back();
        }

        $toast = match ($verifier->verify($order)) {
            PaymentVerifier::PAID => tap(
                ['type' => 'success', 'message' => "Payment confirmed for {$order->reference} — bundle dispatched."],
                function () use ($order, $dispatch): void {
                    $order->update(['payment_status' => Order::PAYMENT_PAID]);
                    $dispatch->fulfillPaid($order);
                },
            ),
            PaymentVerifier::FAILED => tap(
                ['type' => 'error', 'message' => "{$order->reference}: gateway reports the payment failed — order marked failed."],
                fn () => $order->update(['payment_status' => Order::PAYMENT_FAILED]),
            ),
            default => ['type' => 'info', 'message' => "{$order->reference}: payment not confirmed yet — left awaiting. Try again shortly."],
        };

        Inertia::flash('toast', $toast);

        return back();
    }

    /**
     * Manually confirm a storefront payment and dispatch — the admin override for when they know
     * the money arrived but the gateway check is lagging. Only pushes an awaiting order forward
     * (never marks one failed), so it can't destroy a paid order the way a manual "fail" could.
     */
    public function markVerified(Order $order, OrderDispatchService $dispatch): RedirectResponse
    {
        if ($order->payment_status !== Order::PAYMENT_AWAITING) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only orders awaiting payment can be marked verified.']);

            return back();
        }

        $order->update(['payment_status' => Order::PAYMENT_PAID]);
        $dispatch->fulfillPaid($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$order->reference} marked verified — bundle dispatched."]);

        return back();
    }

    /**
     * Delete an awaiting storefront order (spam / abandoned checkout that never paid). Guarded to
     * awaiting-only: a paid or dispatched order carries money and earnings and is never deleted —
     * it's refunded instead.
     */
    public function destroy(Order $order): RedirectResponse
    {
        if ($order->payment_status !== Order::PAYMENT_AWAITING) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only orders awaiting payment can be deleted.']);

            return back();
        }

        $reference = $order->reference;
        $order->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$reference} deleted."]);

        return back();
    }

    /**
     * Export the current filtered view as CSV (DBH's "Export Filter"). Honours every filter the
     * page is showing, including status. XLSX would need the phpspreadsheet package.
     */
    public function export(Request $request, OrderListPresenter $presenter): StreamedResponse
    {
        $segment = $request->query('segment') === OrderListPresenter::SEGMENT_REGULAR
            ? OrderListPresenter::SEGMENT_REGULAR
            : OrderListPresenter::SEGMENT_AGENT;
        $status = (string) $request->query('status', 'all');

        $orders = $presenter->scopedQuery($request, $segment)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('seller')
            ->latest('id')
            ->get();

        $filename = "{$segment}-orders-".now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($orders): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Reference', 'Seller', 'Type', 'Network', 'Capacity (GB)', 'Receiver', 'Customer Price', 'Seller Cost', 'Status', 'Source', 'Payment', 'Upstream Ref', 'Created']);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->reference,
                    $o->seller?->name ?? 'Unknown',
                    class_basename($o->seller_type),
                    strtoupper($o->network),
                    (float) $o->capacity_gb,
                    $o->beneficiary_phone,
                    (float) $o->customer_price,
                    (float) $o->seller_cost,
                    $o->status,
                    $o->source,
                    $o->payment_status,
                    $o->upstream_reference,
                    $o->created_at?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Multi-select bulk bar. Regular: Verify / Mark Verified / Delete (awaiting). Agent: Sync /
     * Retry / Apply status. All logic lives in OrderBulkService, which scopes each action safely.
     */
    public function bulk(Request $request, OrderBulkService $bulk): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:verify,mark-verified,delete,sync,retry,apply-status'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required_if:action,apply-status', 'in:pending,processing,completed,failed,refunded'],
        ]);

        $message = $bulk->apply($data['action'], $data['ids'], $data['status'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Admin refund of a settled order: return the seller's deposit and reverse the earnings.
     * Only a delivered order can be refunded here — pending/processing orders settle or reverse
     * through the upstream pipe, and failed/already-refunded orders gave the money back already.
     */
    public function refund(Request $request, Order $order, OrderSettlementService $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($order->status !== Order::STATUS_COMPLETED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only completed orders can be refunded.']);

            return back();
        }

        $result = $settlement->refund($order, $validated['reason'] ?? null);

        Inertia::flash('toast', [
            'type' => $result['refunded'] ? 'success' : 'error',
            'message' => $result['refunded']
                ? "{$order->reference} refunded — GHS ".number_format($result['amount'], 2).' returned to the seller.'
                : $result['message'],
        ]);

        return back();
    }
}
