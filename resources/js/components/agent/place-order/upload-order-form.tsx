import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { Download, Upload } from "lucide-react";
import { template, upload } from "@/routes/agent/cart";
import { Button } from "@/components/ui/button";

/**
 * Upload a CSV / Excel sheet of orders — A: phone, B: size in GB, C: network (optional). Rows are
 * parsed, validated, and priced server-side; invalid rows are skipped.
 */
export function UploadOrderForm() {
    const form = useForm<{ orders_file: File | null }>({ orders_file: null });
    const [fileName, setFileName] = useState("");

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(upload.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                setFileName("");
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <label
                htmlFor="orders_file"
                className="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-border bg-muted/40 p-6 text-center transition hover:border-brand"
            >
                <Upload className="size-6 text-muted-foreground" />
                <span className="text-sm font-medium text-foreground">{fileName || "Choose a CSV or Excel file"}</span>
                <span className="text-xs text-muted-foreground">A: phone · B: size in GB · C: network (optional)</span>
                <input
                    id="orders_file"
                    type="file"
                    accept=".csv,.xlsx,.xls"
                    className="hidden"
                    onChange={(e) => {
                        const file = e.target.files?.[0] ?? null;
                        form.setData("orders_file", file);
                        setFileName(file?.name ?? "");
                    }}
                />
            </label>
            {form.errors.orders_file && <p className="text-xs text-destructive">{form.errors.orders_file}</p>}

            <a
                href={template.url()}
                className="inline-flex items-center gap-1.5 text-xs font-medium text-brand hover:underline"
            >
                <Download className="size-3.5" />
                Download sample template
            </a>

            <Button type="submit" className="w-full" disabled={form.processing || !form.data.orders_file}>
                Upload &amp; add to cart
            </Button>
        </form>
    );
}
