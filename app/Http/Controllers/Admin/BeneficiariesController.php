<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnapprovedBeneficiary;
use App\Services\Beneficiaries\UnapprovedBeneficiaryService;
use App\Services\Databundleshub\UpstreamClient;
use App\Support\CsvExport;
use App\Support\GhanaMobileNetwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BeneficiariesController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');
        $network = (string) $request->query('network', 'all');
        $search = trim((string) $request->query('q', ''));

        $query = UnapprovedBeneficiary::query()->with('agent');

        if ($status !== 'all' && in_array($status, ['pending', 'submitted', 'approved'], true)) {
            $query->where('status', $status);
        }

        if ($network !== 'all' && in_array($network, ['mtn', 'telecel', 'at'], true)) {
            $query->where('network', $network);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('source_label', 'like', "%{$search}%");
            });
        }

        $counts = [
            'total' => UnapprovedBeneficiary::count(),
            'pending' => UnapprovedBeneficiary::where('status', UnapprovedBeneficiary::STATUS_PENDING)->count(),
            'submitted' => UnapprovedBeneficiary::where('status', UnapprovedBeneficiary::STATUS_SUBMITTED)->count(),
            'approved' => UnapprovedBeneficiary::where('status', UnapprovedBeneficiary::STATUS_APPROVED)->count(),
        ];

        $beneficiaries = $query->latest('last_checked_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (UnapprovedBeneficiary $b): array => [
                'id' => $b->id,
                'phone' => $b->phone,
                'network' => $b->network,
                'networkLabel' => $b->network ? GhanaMobileNetwork::label($b->network) : null,
                'sourceLabel' => $b->source_label ?? ($b->agent ? ($b->agent->store_name ?: $b->agent->name) : 'Direct'),
                'status' => $b->status,
                'failureMessage' => $b->failure_message,
                'checkCount' => $b->check_count,
                'submittedAt' => $b->submitted_at?->format('M j, Y g:i A'),
                'approvedAt' => $b->approved_at?->format('M j, Y g:i A'),
                'lastCheckedAt' => $b->last_checked_at?->format('M j, Y g:i A'),
                'createdAt' => $b->created_at?->format('M j, Y g:i A'),
            ]);

        return Inertia::render('admin/beneficiaries', [
            'beneficiaries' => $beneficiaries,
            'counts' => $counts,
            'filters' => [
                'status' => $status,
                'network' => $network,
                'q' => $search,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $status = (string) $request->query('status', 'all');
        $network = (string) $request->query('network', 'all');
        $search = trim((string) $request->query('q', ''));
        $markSubmitted = $request->boolean('mark_submitted');

        $query = UnapprovedBeneficiary::query()->with('agent');

        $rawIds = $request->input('ids');
        if (!empty($rawIds)) {
            $ids = is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds);
            $ids = array_filter(array_map('intval', $ids), fn (int $id) => $id > 0);
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        if ($status !== 'all' && in_array($status, ['pending', 'submitted', 'approved'], true)) {
            $query->where('status', $status);
        }

        if ($network !== 'all' && in_array($network, ['mtn', 'telecel', 'at'], true)) {
            $query->where('network', $network);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('source_label', 'like', "%{$search}%");
            });
        }

        $items = $query->latest('last_checked_at')->get();

        if ($markSubmitted) {
            $pendingIds = $items->where('status', UnapprovedBeneficiary::STATUS_PENDING)->pluck('id');
            if ($pendingIds->isNotEmpty()) {
                UnapprovedBeneficiary::whereIn('id', $pendingIds)->update([
                    'status' => UnapprovedBeneficiary::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                ]);
            }
        }

        $filename = "beneficiaries-{$status}-".now()->format('Y-m-d_His').'.csv';
        $headers = ['Phone', 'Network', 'Status'];

        $rows = $items->map(fn (UnapprovedBeneficiary $b): array => [
            CsvExport::text($b->phone),
            strtoupper($b->network ?? 'UNKNOWN'),
            ucfirst($b->status),
        ]);

        return CsvExport::download($filename, $headers, $rows);
    }

    public function markSubmitted(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = UnapprovedBeneficiary::whereIn('id', $validated['ids'])
            ->update([
                'status' => UnapprovedBeneficiary::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Marked {$count} numbers as submitted.",
        ]);

        return back();
    }

    public function markApproved(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = UnapprovedBeneficiary::whereIn('id', $validated['ids'])
            ->update([
                'status' => UnapprovedBeneficiary::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Marked {$count} numbers as approved.",
        ]);

        return back();
    }

    public function recheck(
        Request $request,
        UpstreamClient $upstream,
        UnapprovedBeneficiaryService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $beneficiaries = UnapprovedBeneficiary::whereIn('id', $validated['ids'])->get();

        $approvedCount = 0;
        foreach ($beneficiaries as $b) {
            if ($service->recheck($b, $upstream)) {
                $approvedCount++;
            }
        }

        $total = $beneficiaries->count();
        $message = "Checked {$total} numbers upstream: {$approvedCount} approved, ".($total - $approvedCount).' still unapproved.';

        Inertia::flash('toast', [
            'type' => $approvedCount > 0 ? 'success' : 'info',
            'message' => $message,
        ]);

        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        UnapprovedBeneficiary::where('id', $id)->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Record deleted.',
        ]);

        return back();
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $count = UnapprovedBeneficiary::whereIn('id', $validated['ids'])->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Deleted {$count} records.",
        ]);

        return back();
    }
}

