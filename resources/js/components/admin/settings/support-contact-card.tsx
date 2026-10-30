import { useForm } from '@inertiajs/react';
import { updateSupport } from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Admin WhatsApp number behind the agent portal's floating support button. Blank hides the button. */
export function SupportContactCard({ whatsappNumber }: { whatsappNumber: string }) {
    const form = useForm({ whatsapp_number: whatsappNumber });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Support WhatsApp</CardTitle>
                <p className="mt-1 text-sm text-muted-foreground">
                    Agents see a WhatsApp button at the bottom right of their dashboard that opens a chat with this number. Leave it blank to hide the button.
                </p>
            </CardHeader>
            <CardContent>
                <form
                    className="max-w-lg space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(updateSupport().url, { preserveScroll: true });
                    }}
                >
                    <div className="space-y-1.5">
                        <Label htmlFor="support-whatsapp">WhatsApp number</Label>
                        <Input
                            id="support-whatsapp"
                            inputMode="tel"
                            placeholder="e.g. 0241234567"
                            value={form.data.whatsapp_number}
                            onChange={(e) => form.setData('whatsapp_number', e.target.value)}
                        />
                        {form.errors.whatsapp_number ? <p className="text-xs text-danger">{form.errors.whatsapp_number}</p> : null}
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        Save number
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
