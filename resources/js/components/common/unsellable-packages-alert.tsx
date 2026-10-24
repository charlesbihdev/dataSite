import { Link } from "@inertiajs/react";
import { AlertTriangle } from "lucide-react";

export interface UnsellablePackage {
    id: number;
    network: string;
    networkLabel: string | null;
    capacityGb: number;
    minGb: number;
}

/** Red notice listing store packages below the network minimum, so the seller updates them. */
export function UnsellablePackagesAlert({ packages, href }: { packages: UnsellablePackage[]; href: string }) {
    if (packages.length === 0) return null;

    const names = packages.map((p) => `${p.networkLabel ?? p.network} ${p.capacityGb}GB`).join(", ");
    const minimums = [...new Set(packages.map((p) => `${p.networkLabel ?? p.network} bundles start at ${p.minGb}GB`))].join(", ");

    return (
        <div className="flex gap-3 rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-destructive">
            <AlertTriangle className="mt-0.5 size-5 shrink-0" />
            <div className="space-y-1 text-sm">
                <p className="font-semibold">Please update your packages</p>
                <p>
                    {minimums}. Kindly remove {names} from your store so every order goes through smoothly.
                </p>
                <Link href={href} className="inline-block font-semibold underline underline-offset-4">
                    Manage packages
                </Link>
            </div>
        </div>
    );
}
