<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Turns the DateRangePicker's query params (?range=…&from=…&to=…) into a
 * [from, to] Carbon pair. Single source of truth for the preset semantics so
 * every surface (admin orders, agent dashboard) filters identically to what the
 * frontend picker offers.
 */
final class DateRange
{
    /**
     * @return array{0: ?Carbon, 1: ?Carbon} [from, to] — nulls mean "no bound" (all time).
     */
    public static function resolve(Request $request, ?string $range = null): array
    {
        $range ??= (string) $request->query('range', 'all');
        $now = now();

        return match ($range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'custom' => [
                self::parse((string) $request->query('from'))?->startOfDay(),
                self::parse((string) $request->query('to'))?->endOfDay(),
            ],
            default => [null, null],
        };
    }

    private static function parse(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
