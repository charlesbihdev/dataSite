<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountPaymentRequest;
use App\Http\Requests\Admin\AccountRequest;
use App\Http\Requests\Admin\BulkAccountRequest;
use App\Models\Agent;
use App\Models\PricingTier;
use App\Models\Subagent;
use App\Services\Accounts\AccountsPresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Agents and their subagents in one place, switched by the ?type tab. Rows carry the two money
 * pools plus order activity; the admin can create, fund, reset, suspend, delete, and export.
 * Query/stat building lives in AccountsPresenter to keep this controller thin.
 */
class AccountsController extends Controller
{
    public function __construct(private readonly AccountsPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $type = $request->query('type') === 'subagents' ? 'subagents' : 'agents';
        $q = $request->string('q')->trim()->value() ?: null;
        $status = in_array($request->query('status'), ['active', 'suspended'], true)
            ? $request->query('status') : null;

        $rows = $this->presenter->rows($type, $q, $status);

        return Inertia::render('admin/accounts', [
            'type' => $type,
            'filters' => ['q' => $q, 'status' => $status],
            'accounts' => $rows->values()->all(),
            'counts' => ['agents' => Agent::query()->count(), 'subagents' => Subagent::query()->count()],
            'stats' => $this->presenter->stats($type),
            'analytics' => $this->presenter->analytics($rows),
            'tiers' => PricingTier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_default'])->all(),
            'agents' => Agent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    /**
     * Create an agent or subagent, optionally seeding the deposit wallet with an opening balance.
     */
    public function store(AccountRequest $request, string $type): RedirectResponse
    {
        $data = $request->safe()->except('initial_balance');
        $data['is_active'] = $request->boolean('is_active', true);

        // The handle starts equal to the username; changed later in settings.
        if (! empty($data['username'])) {
            $data['slug'] = $data['username'];
        }

        if ($type === 'agents' && empty($data['pricing_tier_id'])) {
            $data['pricing_tier_id'] = PricingTier::where('is_default', true)->value('id');
        }

        $account = $type === 'subagents' ? Subagent::create($data) : Agent::create($data);

        $opening = (float) $request->input('initial_balance', 0);
        if ($opening > 0) {
            $account->walletOrCreate()->credit($opening, 'topup', null, 'Opening balance');
        }

        return $this->redirect($type, "{$account->name} created.");
    }

    /**
     * Manual deposit-wallet adjustment: positive credits, negative debits.
     */
    public function addFunds(AccountPaymentRequest $request, string $type, int $id): RedirectResponse
    {
        $model = $this->resolve($type, $id);
        if ($model === null) {
            return $this->toast('error', 'Account not found.');
        }

        $amount = (float) $request->input('amount');
        $note = $request->input('note') ?: 'Admin adjustment';
        $wallet = $model->walletOrCreate();

        try {
            $amount > 0
                ? $wallet->credit($amount, 'topup', null, $note)
                : $wallet->debit($amount, 'adjustment', null, $note);
        } catch (InsufficientBalanceException) {
            return $this->toast('error', "Deduction exceeds {$model->name}'s balance.");
        }

        $verb = $amount > 0 ? 'Added' : 'Deducted';

        return $this->toast('success', "{$verb} GHS ".number_format(abs($amount), 2)." — {$model->name}.");
    }

    public function toggle(string $type, int $id): RedirectResponse
    {
        $model = $this->resolve($type, $id);
        if ($model === null) {
            return $this->toast('error', 'Account not found.');
        }

        $model->is_active = ! $model->is_active;
        $model->save();

        return $this->toast('success', "{$model->name} ".($model->is_active ? 'activated' : 'suspended').'.');
    }

    public function assignTier(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'pricing_tier_id' => ['required', 'exists:pricing_tiers,id'],
        ]);

        $agent = Agent::find($id);
        if ($agent === null) {
            return $this->toast('error', 'Agent not found.');
        }

        $tier = PricingTier::find($request->input('pricing_tier_id'));
        $agent->update(['pricing_tier_id' => $tier->id]);

        return $this->toast('success', "Assigned tier {$tier->name} to {$agent->name}.");
    }

    public function resetPassword(Request $request, string $type, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $model = $this->resolve($type, $id);
        if ($model === null) {
            return $this->toast('error', 'Account not found.');
        }

        $model->password = $validated['password'];
        $model->save();

        return $this->toast('success', 'Password updated successfully.');
    }

    /**
     * Delete an account, but only when it has no orders (and, for agents, no subagents) that would
     * be orphaned. subagents.agent_id is DB-restricted, so this guard fails loudly rather than 500.
     */
    public function destroy(string $type, int $id): RedirectResponse
    {
        $model = $this->resolve($type, $id);
        if ($model === null) {
            return $this->toast('error', 'Account not found.');
        }

        if (! $this->isDeletable($model)) {
            return $this->toast('error', "{$model->name} has orders or subagents — suspend instead.");
        }

        $name = $model->name;
        $model->wallet()->delete();
        $model->delete();

        return $this->toast('success', "{$name} deleted.");
    }

    /**
     * Apply one action to a set of selected ids. Export streams a CSV; the rest redirect back.
     */
    public function bulk(BulkAccountRequest $request, string $type): RedirectResponse
    {
        $models = $this->collect($type, $request->input('ids'));
        if ($models->isEmpty()) {
            return $this->toast('error', 'No matching accounts.');
        }

        return match ($request->input('action')) {
            'reset' => $this->bulkReset($models, (string) $request->input('password')),
            'suspend' => $this->bulkFlag($models, false),
            'activate' => $this->bulkFlag($models, true),
            'delete' => $this->bulkDelete($models),
            default => $this->toast('error', 'Unknown action.'),
        };
    }

    /**
     * Stream selected accounts (or the whole tab, when no ids are given) as CSV. GET so the browser
     * downloads it directly, no Inertia round-trip.
     */
    public function exportCsv(Request $request, string $type): StreamedResponse
    {
        $ids = array_filter(explode(',', (string) $request->query('ids', '')));
        $query = ($type === 'subagents' ? Subagent::query() : Agent::query())->with('wallet');
        $models = $ids === [] ? $query->orderBy('name')->get() : $query->whereIn('id', $ids)->get();

        return $this->export($models, $type);
    }

    /**
     * @param  EloquentCollection<int, Agent|Subagent>  $models
     */
    private function bulkReset(EloquentCollection $models, string $password): RedirectResponse
    {
        $models->each(fn (Agent|Subagent $m) => tap($m, fn ($x) => $x->update(['password' => $password])));

        return $this->toast('success', $models->count().' password(s) reset.');
    }

    /**
     * @param  EloquentCollection<int, Agent|Subagent>  $models
     */
    private function bulkFlag(EloquentCollection $models, bool $active): RedirectResponse
    {
        $models->each(fn (Agent|Subagent $m) => $m->update(['is_active' => $active]));

        return $this->toast('success', $models->count().' account(s) '.($active ? 'activated' : 'suspended').'.');
    }

    /**
     * @param  EloquentCollection<int, Agent|Subagent>  $models
     */
    private function bulkDelete(EloquentCollection $models): RedirectResponse
    {
        $deletable = $models->filter(fn (Agent|Subagent $m) => $this->isDeletable($m));
        $deletable->each(function (Agent|Subagent $m): void {
            $m->wallet()->delete();
            $m->delete();
        });

        $blocked = $models->count() - $deletable->count();
        $message = $deletable->count().' deleted.'.($blocked > 0 ? " {$blocked} kept (had orders/subagents)." : '');

        return $this->toast($blocked > 0 ? 'error' : 'success', $message);
    }

    /**
     * @param  EloquentCollection<int, Agent|Subagent>  $models
     */
    private function export(EloquentCollection $models, string $type): StreamedResponse
    {
        $file = "{$type}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($models): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Username', 'Status', 'Wallet', 'Orders']);
            foreach ($models as $m) {
                fputcsv($out, [
                    $this->csvSafe($m->name),
                    $this->csvSafe($m->phone),
                    $this->csvSafe($m->email),
                    $this->csvSafe($m->username),
                    $m->is_active ? 'active' : 'suspended',
                    number_format((float) ($m->wallet?->balance ?? 0), 2),
                    (int) $m->orders()->count(),
                ]);
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv']);
    }

    /**
     * Guard a spreadsheet cell against formula interpretation (CSV injection). A leading
     * tab keeps values like "+233…" as plain text so phones aren't parsed as formulas.
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "\t".$value;
        }

        return $value;
    }

    private function isDeletable(Agent|Subagent $model): bool
    {
        if ($model->orders()->exists()) {
            return false;
        }

        return $model instanceof Subagent || ! $model->subagents()->exists();
    }

    private function resolve(string $type, int $id): Agent|Subagent|null
    {
        return $type === 'subagents' ? Subagent::query()->find($id) : Agent::query()->find($id);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return EloquentCollection<int, Agent|Subagent>
     */
    private function collect(string $type, array $ids): EloquentCollection
    {
        $query = $type === 'subagents' ? Subagent::query() : Agent::query();

        return $query->with('wallet')->whereIn('id', $ids)->get();
    }

    private function redirect(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('admin.accounts', ['type' => $type]);
    }

    private function toast(string $level, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $level, 'message' => $message]);

        return back();
    }
}
