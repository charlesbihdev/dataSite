import { Check, Copy, ListOrdered } from "lucide-react";
import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

function CodeSnippet({ code }: { code: string }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="relative rounded-lg border border-border bg-muted/40 p-3 font-mono text-xs">
            <button
                type="button"
                onClick={handleCopy}
                className="absolute top-2.5 right-2.5 flex items-center gap-1 rounded bg-card px-2 py-1 text-xs text-muted-foreground border border-border transition-colors hover:text-foreground"
            >
                {copied ? (
                    <Check className="size-3 text-success" />
                ) : (
                    <Copy className="size-3" />
                )}
                <span>{copied ? "Copied" : "Copy"}</span>
            </button>
            <pre className="overflow-x-auto pr-16 text-foreground">{code}</pre>
        </div>
    );
}

export function EndpointDocCard({ baseUrl }: { baseUrl: string }) {
    const [activeTab, setActiveTab] = useState<
        "purchase" | "status" | "packages"
    >("purchase");

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

    const curlPackages = `curl -X GET "${baseUrl}/developer/data-packages?network=mtn" \\
  -H "X-API-Key: dsk_your_api_key_here"`;

    const jsonPackagesResponse = `{
  "success": true,
  "data": [
    {
      "capacity": "5",
      "mb": "5120",
      "price": "22.50",
      "network": "MTN",
      "pricePerGB": "4.50"
    }
  ],
  "meta": {
    "totalPackages": 1,
    "supportedNetworks": ["MTN", "TELECEL", "AT"]
  }
}`;

    return (
        <Card>
            <CardHeader className="border-b border-border pb-4">
                <div className="flex items-center gap-2">
                    <ListOrdered className="size-5 text-brand" />
                    <CardTitle className="text-base">
                        Endpoint Reference
                    </CardTitle>
                </div>
                <p className="text-xs text-muted-foreground">
                    Detailed request schemas and response examples for all
                    developer endpoints.
                </p>
            </CardHeader>

            <CardContent className="space-y-6 pt-6">
                {/* Tabs */}
                <div className="flex flex-wrap gap-2 border-b border-border pb-3">
                    <button
                        type="button"
                        onClick={() => setActiveTab("purchase")}
                        className={cn(
                            "rounded-md px-3 py-1.5 text-xs font-medium transition-colors",
                            activeTab === "purchase"
                                ? "bg-brand text-brand-fg"
                                : "text-muted-foreground hover:bg-muted hover:text-foreground",
                        )}
                    >
                        1. Create Order
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
                        2. Check Order Status
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab("packages")}
                        className={cn(
                            "rounded-md px-3 py-1.5 text-xs font-medium transition-colors",
                            activeTab === "packages"
                                ? "bg-brand text-brand-fg"
                                : "text-muted-foreground hover:bg-muted hover:text-foreground",
                        )}
                    >
                        3. Package List & Prices
                    </button>
                </div>

                {/* Tab 1: Create Order */}
                {activeTab === "purchase" && (
                    <div className="space-y-4">
                        <div>
                            <h4 className="text-sm font-semibold text-foreground">
                                Purchase Data Bundle (POST)
                            </h4>
                            <p className="text-xs text-muted-foreground">
                                Debits your wallet deposit at your tier price
                                and pushes the order for immediate delivery.
                                Endpoint:{" "}
                                <code className="font-mono text-foreground">
                                    {baseUrl}/create_order
                                </code>{" "}
                                (alias{" "}
                                <code className="font-mono text-foreground">
                                    /developer/purchase
                                </code>
                                ).
                            </p>
                        </div>

                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-muted/40 text-muted-foreground border-b border-border">
                                    <tr>
                                        <th className="p-2.5 font-medium">
                                            Field
                                        </th>
                                        <th className="p-2.5 font-medium">
                                            Type
                                        </th>
                                        <th className="p-2.5 font-medium">
                                            Required
                                        </th>
                                        <th className="p-2.5 font-medium">
                                            Description
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border text-foreground">
                                    <tr>
                                        <td className="p-2.5 font-mono font-medium">
                                            phoneNumber
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            string
                                        </td>
                                        <td className="p-2.5 font-medium text-success">
                                            Yes
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Beneficiary 10-digit phone number
                                            (e.g. 0551234567)
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="p-2.5 font-mono font-medium">
                                            network
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            string
                                        </td>
                                        <td className="p-2.5 font-medium text-success">
                                            Yes
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Network code: mtn, telecel, or at
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="p-2.5 font-mono font-medium">
                                            capacity
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            integer
                                        </td>
                                        <td className="p-2.5 font-medium text-success">
                                            Yes
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Allowed bundle capacity in GB
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="p-2.5 font-mono font-medium">
                                            idempotencyKey
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            string
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Optional
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Client reference to guarantee
                                            exactly-once purchase execution
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-xs font-semibold text-foreground">
                                cURL Request
                            </span>
                            <CodeSnippet code={curlCreate} />
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-xs font-semibold text-foreground">
                                Response (201 Created)
                            </span>
                            <CodeSnippet code={jsonCreateResponse} />
                        </div>
                    </div>
                )}

                {/* Tab 2: Check Status */}
                {activeTab === "status" && (
                    <div className="space-y-4">
                        <div>
                            <h4 className="text-sm font-semibold text-foreground">
                                Check Order Status (GET)
                            </h4>
                            <p className="text-xs text-muted-foreground">
                                Poll the status of an order by reference.
                                Endpoint:{" "}
                                <code className="font-mono text-foreground">
                                    {baseUrl}/order-status/:reference
                                </code>{" "}
                                (alias{" "}
                                <code className="font-mono text-foreground">
                                    /developer/purchase-status/:reference
                                </code>
                                ).
                            </p>
                        </div>

                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-muted/40 text-muted-foreground border-b border-border">
                                    <tr>
                                        <th className="p-2.5 font-medium">
                                            Status Value
                                        </th>
                                        <th className="p-2.5 font-medium">
                                            Description
                                        </th>
                                        <th className="p-2.5 font-medium">
                                            Terminal?
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border text-foreground">
                                    <tr>
                                        <td className="p-2.5 font-mono text-warning">
                                            pending
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Order received, awaiting upstream
                                            transmission
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            No
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="p-2.5 font-mono text-brand">
                                            processing
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Accepted by telecom network,
                                            fulfillment in progress
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            No
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="p-2.5 font-mono text-success">
                                            completed
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Data bundle successfully credited to
                                            recipient
                                        </td>
                                        <td className="p-2.5 text-success font-medium">
                                            Yes
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="p-2.5 font-mono text-danger">
                                            failed
                                        </td>
                                        <td className="p-2.5 text-muted-foreground">
                                            Delivery failed; wallet deposit
                                            refunded
                                        </td>
                                        <td className="p-2.5 text-danger font-medium">
                                            Yes
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-xs font-semibold text-foreground">
                                cURL Request
                            </span>
                            <CodeSnippet code={curlStatus} />
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-xs font-semibold text-foreground">
                                Response (200 OK)
                            </span>
                            <CodeSnippet code={jsonStatusResponse} />
                        </div>
                    </div>
                )}

                {/* Tab 3: Package List & Prices */}
                {activeTab === "packages" && (
                    <div className="space-y-4">
                        <div>
                            <h4 className="text-sm font-semibold text-foreground">
                                Package List & Live Prices (GET)
                            </h4>
                            <p className="text-xs text-muted-foreground">
                                Retrieve the live pricing rate card configured
                                for your agent account tier. Endpoint:{" "}
                                <code className="font-mono text-foreground">
                                    {baseUrl}/developer/data-packages
                                </code>{" "}
                                (alias{" "}
                                <code className="font-mono text-foreground">
                                    /data-packages
                                </code>
                                ).
                            </p>
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-xs font-semibold text-foreground">
                                cURL Request
                            </span>
                            <CodeSnippet code={curlPackages} />
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-xs font-semibold text-foreground">
                                Response (200 OK)
                            </span>
                            <CodeSnippet code={jsonPackagesResponse} />
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
