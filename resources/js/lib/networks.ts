/**
 * Client-side network detection for the Place-Order form. Mirrors the server's
 * GhanaMobileNetwork prefix logic but reads the prefix table from the `networks` prop, so the
 * single source of truth stays on the server (ENGINEERING_PRINCIPLES: no duplicated constants).
 */
export interface NetworkMeta {
    code: string;
    label: string;
    prefixes: string[];
    sizes: number[];
    min_gb: number;
    max_gb: number;
}

/** Reduce any Ghanaian input to a 10-digit 0XXXXXXXXX form, or '' if not a valid mobile number. */
export function normalizeMsisdn(raw: string): string {
    let digits = raw.replace(/\D+/g, "");
    if (digits.startsWith("233") && digits.length === 12) {
        digits = "0" + digits.slice(3);
    } else if (digits.length === 9 && (digits[0] === "2" || digits[0] === "5")) {
        digits = "0" + digits;
    }

    return digits.length === 10 && digits[0] === "0" ? digits : "";
}

/** Resolve the network for a phone number, or null when the prefix is unknown/incomplete. */
export function detectNetwork(phone: string, networks: NetworkMeta[]): NetworkMeta | null {
    const normalized = normalizeMsisdn(phone);
    if (normalized === "") {
        return null;
    }
    const prefix = normalized.slice(0, 3);

    return networks.find((n) => n.prefixes.includes(prefix)) ?? null;
}
