<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Resellers\ProfitSplitReport;
use App\Services\Resellers\ResellerTreePresenter;
use App\Support\CsvExport;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only Resellers section: who sits under which agent and what they earn, plus a per-sale
 * audit of how each subagent sale's profit was split between subagent, agent and platform.
 */
class ResellersController extends Controller
{
    public function index(Request $request, ResellerTreePresenter $tree): Response
    {
        $search = trim((string) $request->query('q', ''));
        [$from, $to] = DateRange::resolve($request);

        return Inertia::render('admin/resellers/index', [
            'agents' => $tree->agents($search, $from, $to),
            'stats' => $tree->stats($from, $to),
            'filters' => [
                'q' => $search,
                'range' => (string) $request->query('range', 'all'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }

    public function splits(Request $request, ProfitSplitReport $report): Response
    {
        $filters = $report->filters($request);
        $query = $report->query($request, $filters);

        return Inertia::render('admin/resellers/profit-splits', [
            'splits' => $report->withRelations(clone $query)
                ->latest('orders.id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Order $order): array => $report->present($order)),
            'stats' => $report->stats($query),
            'filters' => $filters,
            'agents' => $report->agentOptions(),
        ]);
    }

    public function exportSplits(Request $request, ProfitSplitReport $report): StreamedResponse
    {
        $orders = $report->withRelations($report->query($request, $report->filters($request)))->lazyById(500, 'orders.id', 'id');

        $rows = (function () use ($orders, $report): \Generator {
            foreach ($orders as $order) {
                $row = $report->present($order);
                yield [
                    $row['date'], $row['reference'], $row['subagent'], $row['agent'], $row['orderStatus'],
                    $row['customerPaid'], $row['subagentShare'], $row['agentShare'], $row['platformShare'],
                    $row['dbhCost'], $row['dbhCost'] === null ? '' : ($row['dbhActual'] ? 'actual' : 'expected'),
                    $row['settlement'], $row['issues'] === [] ? 'OK' : 'CHECK', implode('; ', $row['issues']),
                ];
            }
        })();

        return CsvExport::download('profit-splits-'.now()->format('Y-m-d').'.csv', [
            'Date', 'Order', 'Subagent', 'Agent', 'Order status', 'Customer paid', 'Subagent share',
            'Agent share', 'Platform share', 'DBH cost', 'DBH cost source', 'Settlement', 'Check', 'Issues',
        ], $rows);
    }
}
