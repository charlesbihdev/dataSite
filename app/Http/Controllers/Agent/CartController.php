<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AddCartItemRequest;
use App\Http\Requests\Agent\BulkCartRequest;
use App\Http\Requests\Agent\UploadCartRequest;
use App\Services\Cart\CartCheckoutService;
use App\Services\Cart\CartService;
use App\Services\Cart\OrderFileParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The agent's Place-Order basket: three ways in (single, bulk paste, file upload), remove a line,
 * and checkout. Every action mutates the session cart via CartService and redirects back to the
 * dashboard, where the refreshed cart re-renders (the server-driven Inertia way).
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function store(AddCartItemRequest $request): RedirectResponse
    {
        $error = $this->cart->add($request->user(), (string) $request->input('beneficiary_phone'), (int) $request->input('bundle_size'));

        return $error !== null
            ? $this->toast('error', $error)
            : $this->toast('success', 'Bundle added to cart.');
    }

    public function storeBulk(BulkCartRequest $request): RedirectResponse
    {
        $rows = $this->parseLines((string) $request->input('bulk_orders_text'));

        return $this->summarize($this->cart->addMany($request->user(), $rows));
    }

    public function upload(UploadCartRequest $request, OrderFileParser $parser): RedirectResponse
    {
        return $this->summarize($this->cart->addMany($request->user(), $parser->parse($request->file('orders_file'))));
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->cart->remove($id);

        return $this->toast('success', 'Cart item removed.');
    }

    public function checkout(Request $request, CartCheckoutService $checkout): RedirectResponse
    {
        $result = $checkout->checkout($request->user());

        if ($result['error'] !== null) {
            return $this->toast('error', $result['error']);
        }

        $message = "{$result['placed']} order(s) placed."
            .($result['failed'] > 0 ? " {$result['failed']} could not be placed and remain in your cart." : '');

        return $this->toast($result['failed'] > 0 ? 'warning' : 'success', $message);
    }

    /**
     * Split pasted text into [phone, sizeGb] rows: one order per line, "phone<space>size".
     *
     * @return list<array{0: string, 1: string}>
     */
    private function parseLines(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (is_array($parts) && count($parts) >= 2 && $parts[0] !== '') {
                $rows[] = [$parts[0], $parts[1]];
            }
        }

        return $rows;
    }

    /**
     * @param  array{added: int, skipped: int}  $result
     */
    private function summarize(array $result): RedirectResponse
    {
        if ($result['added'] === 0) {
            return $this->toast('error', 'No valid orders were found to add.');
        }

        $message = "Added {$result['added']} order(s) to cart."
            .($result['skipped'] > 0 ? " Skipped {$result['skipped']} invalid line(s)." : '');

        return $this->toast($result['skipped'] > 0 ? 'warning' : 'success', $message);
    }

    private function toast(string $level, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $level, 'message' => $message]);

        return to_route('agent.dashboard');
    }
}
