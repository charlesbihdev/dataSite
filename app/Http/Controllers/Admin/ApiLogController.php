<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpstreamApiLog;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only audit of every Databundleshub call (create_order + status polls). The observability
 * tool for the poll-based upstream pipe: what we sent, what came back, HTTP status, and duration.
 * Defaults to the last 30 days; "From" can reach back up to a year to keep the query bounded.
 */
class ApiLogController extends Controller
{
    public function index(Request $request): Response
    {
        $network = (string) $request->query('network', 'all');
        $result = (string) $request->query('result', 'all');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        // Default window: last 30 days. A "From" older than a year is clamped so the table stays bounded.
        $earliest = now()->subYear()->startOfDay();
        $fromDate = $from !== '' ? max($earliest, Carbon::parse($from)->startOfDay()) : now()->subDays(30)->startOfDay();

        $query = UpstreamApiLog::query()
            ->where('created_at', '>=', $fromDate)
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($network !== 'all', fn ($q) => $q->where('network', $network))
            ->when($result === 'success', fn ($q) => $q->where('success', true))
            ->when($result === 'failed', fn ($q) => $q->where('success', false));

        $statsRow = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as ok')
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            ->selectRaw('AVG(duration_ms) as avg_ms')
            ->first();

        $logs = (clone $query)
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (UpstreamApiLog $log): array => $this->present($log));

        return Inertia::render('admin/api-logs', [
            'logs' => $logs,
            'filters' => [
                'network' => $network,
                'result' => $result,
                'from' => $from,
                'to' => $to,
            ],
            'networks' => GhanaMobileNetwork::order(),
            'stats' => [
                'total' => (int) ($statsRow->total ?? 0),
                'success' => (int) ($statsRow->ok ?? 0),
                'failed' => (int) ($statsRow->failed ?? 0),
                'avgMs' => (int) round((float) ($statsRow->avg_ms ?? 0)),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(UpstreamApiLog $log): array
    {
        return [
            'id' => $log->id,
            'time' => $log->created_at?->format('M j, Y g:i A'),
            'network' => $log->network,
            'operation' => $log->operation,
            'success' => $log->success,
            'httpStatus' => $log->http_status,
            'error' => $log->error_message,
            'outcome' => $log->outcome,
            'requestUrl' => $log->request_url,
            'requestBody' => $log->request_payload !== null ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
            'responseBody' => $log->response_body,
            'durationMs' => $log->duration_ms,
        ];
    }
}
