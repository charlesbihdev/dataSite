import { TriangleAlert } from "lucide-react";

/**
 * Inline warning shown under a secret field when its stored value can't be decrypted (the app's
 * encryption key changed). The field stays editable — the admin just types a new value to replace it.
 */
export function SecretUnreadableNotice({
    show,
    label = "saved value",
}: {
    show?: boolean;
    label?: string;
}) {
    if (!show) {
        return null;
    }

    return (
        <p className="mt-1.5 inline-flex items-start gap-1.5 text-xs text-warning">
            <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
            <span>
                The {label} can’t be read (the app encryption key changed).
                Enter a new value to replace it.
            </span>
        </p>
    );
}
