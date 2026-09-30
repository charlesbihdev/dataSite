<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\UpstreamApiLog;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only API activity, two sources on one page: "incoming" (resellers calling our API) and
 * "upstream" (our failures calling Databundleshub, errors only). Defaults to the last 30 days.
 */
class ApiLogController extends Controller
{
    public function index(Request $request): Response
    {
        $source = $request->query('source') === 'upstream' ? 'upstream' : 'incoming';
        $network = (string) $request->query('network', 'all');
        $result = (string) $request->query('result', 'all');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        // Default window: last 30 days. A "From" older than a year is clamped so the table stays bounded.
        $earliest = now()->subYear()->startOfDay();
        $fromDate = $from !== '' ? max($earliest, Carbon::parse($from)->startOfDay()) : now()->subDays(30)->startOfDay();

        $applyCommon = fn ($query) => $query
            ->where('created_at', '>=', $fromDate)
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($network !== 'all', fn ($q) => $q->where('network', $network))
            ->when($result === 'success', fn ($q) => $q->where('success', true))
            ->when($result === 'failed', fn ($q) => $q->where('success', false));

        [$logs, $stats] = $source === 'upstream'
            ? $this->upstream($applyCommon)
            : $this->incoming($applyCommon);

        return Inertia::render('admin/api-logs', [
            'source' => $source,
            'logs' => $logs,
            'filters' => [
                'network' => $network,
                'result' => $result,
                'from' => $from,
                'to' => $to,
            ],
            'networks' => GhanaMobileNetwork::order(),
            'stats' => $stats,
        ]);
    }

    /** @return array{0: \Illuminate\Contracts\Pagination\LengthAwarePaginator, 1: array<string, int>} */
    private function incoming(callable $applyCommon): array
    {
        $query = $applyCommon(ApiRequestLog::query()->with('seller'));
        $stats = $this->stats($query);

        $logs = (clone $query)
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ApiRequestLog $log): array => [
                'id' => $log->id,
                'time' => $log->created_at?->format('M j, Y g:i A'),
                'caller' => $this->callerLabel($log),
                'network' => $log->network,
                'success' => $log->success,
                'httpStatus' => $log->http_status,
                'error' => $log->error_message,
                'endpoint' => trim($log->method.' '.$log->endpoint),
                'requestBody' => $log->request_payload !== null
                    ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : null,
                'responseBody' => $log->response_body,
                'durationMs' => $log->duration_ms,
            ]);

        return [$logs, $stats];
    }

    /** @return array{0: \Illuminate\Contracts\Pagination\LengthAwarePaginator, 1: array<string, int>} */
    private function upstream(callable $applyCommon): array
    {
        $query = $applyCommon(UpstreamApiLog::query());
        $stats = $this->stats($query);

        $logs = (clone $query)
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (UpstreamApiLog $log): array => [
                'id' => $log->id,
                'time' => $log->created_at?->format('M j, Y g:i A'),
                'caller' => $log->operation === 'create' ? 'Place order' : 'Status poll',
                'network' => $log->network,
                'success' => $log->success,
                'httpStatus' => $log->http_status,
                'error' => $log->error_message,
                'endpoint' => $log->request_url,
                'requestBody' => $log->request_payload !== null
                    ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : null,
                'responseBody' => $log->response_body,
                'durationMs' => $log->duration_ms,
            ]);

        return [$logs, $stats];
    }

    /**
     * @return array<string, int>
     */
    private function stats($query): array
    {
        $row = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as ok')
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            ->selectRaw('AVG(duration_ms) as avg_ms')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'success' => (int) ($row->ok ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'avgMs' => (int) round((float) ($row->avg_ms ?? 0)),
        ];
    }

    private function callerLabel(ApiRequestLog $log): string
    {
        $seller = $log->seller;
        if ($seller === null) {
            return 'Unauthorized key';
        }

        $tier = class_basename($seller);

        return trim(($seller->name ?? 'Reseller').' ('.$tier.')');
    }
}
