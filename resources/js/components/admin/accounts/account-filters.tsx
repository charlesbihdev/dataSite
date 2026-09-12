import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { index as accountsIndex } from '@/actions/App/Http/Controllers/Admin/AccountsController';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { AccountType } from './types';

interface Filters {
    q: string | null;
    status: string | null;
}

// Search + status filter. Search is debounced; status is an instant segmented control. Both
// reload the page server-side (the Inertia way) preserving the active tab.
export function AccountFilters({ type, filters }: { type: AccountType; filters: Filters }) {
    const [q, setQ] = useState(filters.q ?? '');

    useEffect(() => {
        const handle = setTimeout(() => {
            if ((filters.q ?? '') === q) return;
            reload({ q, status: filters.status });
        }, 300);
        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const reload = (next: { q: string | null; status: string | null }) => {
        router.get(
            accountsIndex.url(),
            { type, q: next.q || undefined, status: next.status || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const statusButton = (value: string | null, label: string) => (
        <button
            type="button"
            onClick={() => reload({ q, status: value })}
            className={cn(
                'rounded-md px-3 py-1.5 text-sm font-medium',
                (filters.status ?? null) === value ? 'bg-brand text-brand-fg' : 'text-muted-foreground hover:bg-muted',
            )}
        >
            {label}
        </button>
    );

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search name, phone, email"
                    className="w-64 rounded-full pl-9"
                />
            </div>
            <div className="flex items-center gap-1">
                {statusButton(null, 'All')}
                {statusButton('active', 'Active')}
                {statusButton('suspended', 'Suspended')}
            </div>
        </div>
    );
}
