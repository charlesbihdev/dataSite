<?php

namespace App\Services\Payments;

/**
 * Gateways require an email to open a checkout, but our payers often have only a phone. Use the real
 * address when valid, else synthesize one from the phone + a real domain (never the raw app host,
 * which is "localhost"/an IP in dev and gets rejected as an invalid email).
 */
final class PayerEmail
{
    public static function resolve(?string $email, ?string $phone): string
    {
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?: 'customer';
        $candidate = $digits.'@'.self::domain();

        return filter_var($candidate, FILTER_VALIDATE_EMAIL) ? $candidate : 'customer@example.com';
    }

    private static function domain(): string
    {
        // Prefer the configured mail-from domain, then a real app host; fall back to a reserved,
        // always-valid placeholder so the gateway never rejects the synthesized address.
        $from = (string) config('mail.from.address');
        $fromDomain = str_contains($from, '@') ? substr(strrchr($from, '@'), 1) : '';
        if ($fromDomain !== '' && str_contains($fromDomain, '.')) {
            return $fromDomain;
        }

        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($appHost !== '' && str_contains($appHost, '.') && ! filter_var($appHost, FILTER_VALIDATE_IP)) {
            return $appHost;
        }

        return 'example.com';
    }
}
