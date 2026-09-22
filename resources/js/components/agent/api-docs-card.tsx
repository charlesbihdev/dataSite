import { BookOpen, Check, Copy, Globe, Shield, Terminal } from "lucide-react";
import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

interface ApiDocsCardProps {
    baseUrl: string;
}

function CodeSnippet({ code }: { code: string }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="relative rounded-lg border border-border bg-muted/50 p-3 font-mono text-xs">
            <button
                type="button"
                onClick={handleCopy}
                className="absolute top-2.5 right-2.5 flex items-center gap-1 rounded bg-card px-2 py-1 text-xs text-muted-foreground transition-colors hover:text-foreground border border-border"
            >
                {copied ? (
                    <>
                        <Check className="size-3 text-success" />
                        <span>Copied</span>
                    </>
                ) : (
                    <>
                        <Copy className="size-3" />
                        <span>Copy</span>
                    </>
                )}
            </button>
            <pre className="overflow-x-auto pr-16 text-foreground">{code}</pre>
        </div>
    );
}

export function ApiDocsCard({ baseUrl }: ApiDocsCardProps) {
    const [activeTab, setActiveTab] = useState<"create" | "status">("create");

    const curlCreate = `curl -X POST "${baseUrl}/create_order" \\
  -H "X-API-Key: dsk_your_api_key_here" \\
  -H "Content-Type: application/json" \\
  -d '{
    "phoneNumber": "0551234567",
    "network": "mtn",
    "capacity": 5,
    "idempotencyKey": "TXN_123456"
  }'`;

    const jsonCreateResponse = `{
  "success": true,
  "data": {
    "reference": "DS-A1B2C3D4E5",
    "idempotencyKey": "TXN_123456",
    "network": "MTN",
    "capacity": 5,
    "phoneNumber": "0551234567",
    "price": 22.50,
    "orderStatus": "processing",
    "isCompleted": false,
    "message": "Order is being processed. Check the status endpoint shortly.",
    "duplicate": false,
    "createdAt": "2026-09-22T16:00:00Z"
  }
}`;

    const curlStatus = `curl -X GET "${baseUrl}/order-status/DS-A1B2C3D4E5" \\
  -H "X-API-Key: dsk_your_api_key_here"`;

    const jsonStatusResponse = `{
  "success": true,
  "data": {
    "reference": "DS-A1B2C3D4E5",
    "network": "MTN",
    "capacity": 5,
    "phoneNumber": "0551234567",
    "price": 22.50,
    "orderStatus": "completed",
    "isCompleted": true,
    "message": "Order delivered successfully."
  }
}`;

    return (
        <Card>
            <CardHeader className="border-b border-border pb-4">
                <div className="flex items-center gap-2">
                    <BookOpen className="size-5 text-brand" />
                    <CardTitle className="text-base">
                        API Documentation & Quick Start
                    </CardTitle>
                </div>
                <p className="text-xs text-muted-foreground">
                    Integrate bundle purchases directly into your POS, website,
                    or mobile application.
                </p>
            </CardHeader>

            <CardContent className="space-y-6 pt-6">
                {/* Protocol summary */}
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-border p-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <Globe className="size-3.5" />
                            <span>Base URL</span>
                        </div>
                        <p className="mt-1 font-mono text-xs font-medium text-foreground break-all">
                            {baseUrl}
                        </p>
                    </div>

                    <div className="rounded-lg border border-border p-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <Shield className="size-3.5" />
                            <span>Authentication</span>
                        </div>
                        <p className="mt-1 font-mono text-xs font-medium text-foreground">
                            X-API-Key: dsk_...
                        </p>
                    </div>

                    <div className="rounded-lg border border-border p-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <Terminal className="size-3.5" />
                            <span>Rate Limit</span>
                        </div>
                        <p className="mt-1 text-xs font-medium text-foreground">
                            60 requests / minute
                        </p>
                    </div>
                </div>

                {/* Tab selector */}
                <div className="space-y-4">
                    <div className="flex gap-2 border-b border-border pb-2">
                        <button
                            type="button"
                            onClick={() => setActiveTab("create")}
                            className={cn(
                                "rounded-md px-3 py-1.5 text-xs font-medium transition-colors",
                                activeTab === "create"
                                    ? "bg-brand text-brand-fg"
                                    : "text-muted-foreground hover:bg-muted hover:text-foreground",
                            )}
                        >
                            POST /create_order
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab("status")}
                            className={cn(
                                "rounded-md px-3 py-1.5 text-xs font-medium transition-colors",
                                activeTab === "status"
                                    ? "bg-brand text-brand-fg"
                                    : "text-muted-foreground hover:bg-muted hover:text-foreground",
                            )}
                        >
                            GET /order-status/:reference
                        </button>
                    </div>

                    {activeTab === "create" ? (
                        <div className="space-y-4">
                            <div>
                                <h4 className="text-sm font-semibold text-foreground">
                                    Place Order (Synchronous)
                                </h4>
                                <p className="text-xs text-muted-foreground">
                                    Debits your wallet deposit at your tier
                                    price and attempts immediate fulfillment.
                                    Also available at alias{" "}
                                    <code className="font-mono text-foreground">
                                        /developer/purchase
                                    </code>
                                    .
                                </p>
                            </div>

                            <div className="space-y-1.5">
                                <span className="text-xs font-medium text-foreground">
                                    Request Body Fields
                                </span>
                                <div className="overflow-x-auto rounded-lg border border-border">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-muted text-muted-foreground border-b border-border">
                                            <tr>
                                                <th className="p-2 font-medium">
                                                    Field
                                                </th>
                                                <th className="p-2 font-medium">
                                                    Type
                                                </th>
                                                <th className="p-2 font-medium">
                                                    Required
                                                </th>
                                                <th className="p-2 font-medium">
                                                    Description
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            <tr>
                                                <td className="p-2 font-mono font-medium text-foreground">
                                                    phoneNumber
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    string
                                                </td>
                                                <td className="p-2 text-success font-medium">
                                                    Yes
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    Recipient number (e.g.
                                                    0551234567)
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="p-2 font-mono font-medium text-foreground">
                                                    network
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    string
                                                </td>
                                                <td className="p-2 text-success font-medium">
                                                    Yes
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    mtn, telecel, or at
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="p-2 font-mono font-medium text-foreground">
                                                    capacity
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    integer
                                                </td>
                                                <td className="p-2 text-success font-medium">
                                                    Yes
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    Package capacity in whole GB
                                                    (1 - 200)
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="p-2 font-mono font-medium text-foreground">
                                                    idempotencyKey
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    string
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    Optional
                                                </td>
                                                <td className="p-2 text-muted-foreground">
                                                    Client reference to prevent
                                                    duplicate charges
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <span className="text-xs font-medium text-foreground">
                                    Example Request
                                </span>
                                <CodeSnippet code={curlCreate} />
                            </div>

                            <div className="space-y-1.5">
                                <span className="text-xs font-medium text-foreground">
                                    Example Response (201 Created)
                                </span>
                                <CodeSnippet code={jsonCreateResponse} />
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <h4 className="text-sm font-semibold text-foreground">
                                    Track Order Status
                                </h4>
                                <p className="text-xs text-muted-foreground">
                                    Poll status of a previously placed order
                                    using its reference. Also available at alias{" "}
                                    <code className="font-mono text-foreground">
                                        /developer/purchase-status/:reference
                                    </code>
                                    .
                                </p>
                            </div>

                            <div className="space-y-1.5">
                                <span className="text-xs font-medium text-foreground">
                                    Example Request
                                </span>
                                <CodeSnippet code={curlStatus} />
                            </div>

                            <div className="space-y-1.5">
                                <span className="text-xs font-medium text-foreground">
                                    Example Response (200 OK)
                                </span>
                                <CodeSnippet code={jsonStatusResponse} />
                            </div>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
