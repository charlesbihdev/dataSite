import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { Upload } from "lucide-react";
import { upload } from "@/routes/agent/cart";
import { Button } from "@/components/ui/button";

/**
 * Upload a CSV / Excel sheet of orders — column A: phone, column B: size in GB. Rows are parsed,
 * validated, and priced server-side; invalid rows are skipped.
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
                <span className="text-xs text-muted-foreground">Column A: phone · Column B: size in GB</span>
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

            <Button type="submit" className="w-full" disabled={form.processing || !form.data.orders_file}>
                Upload &amp; add to cart
            </Button>
        </form>
    );
}
