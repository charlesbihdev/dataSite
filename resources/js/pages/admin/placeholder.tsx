import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// Shared stub for admin sections not yet built. Each is a real route in the sidebar so the
// whole backoffice is navigable; we replace these one clean slice at a time.
export default function AdminPlaceholder({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <>
            <Head title={`Admin — ${title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                    <p className="text-sm text-muted-foreground">{description}</p>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Coming next</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            This section is scaffolded and routed. It will be built in its own slice.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
