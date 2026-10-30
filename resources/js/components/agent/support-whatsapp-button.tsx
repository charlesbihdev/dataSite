import { usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';

/**
 * Floating bottom-right link to the admin's WhatsApp. The URL is only shared for signed-in agents
 * and only once an admin has saved a number (Settings → Support WhatsApp), otherwise nothing renders.
 */
export function SupportWhatsappButton() {
    const { supportWhatsappUrl } = usePage().props;

    if (!supportWhatsappUrl) {
        return null;
    }

    return (
        <a
            href={supportWhatsappUrl}
            target="_blank"
            rel="noreferrer"
            aria-label="Chat with admin on WhatsApp"
            title="Chat with admin on WhatsApp"
            className="fixed right-4 bottom-4 z-40 inline-flex items-center gap-2 rounded-full bg-whatsapp px-4 py-3 text-sm font-medium text-white shadow-lg transition hover:bg-whatsapp-hover focus-visible:ring-2 focus-visible:ring-whatsapp focus-visible:ring-offset-2 focus-visible:outline-none print:hidden"
        >
            <MessageCircle className="size-5 shrink-0" />
            <span className="hidden sm:inline">WhatsApp admin</span>
        </a>
    );
}
