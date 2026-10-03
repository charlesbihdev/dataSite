<?php

namespace App\Services\Storefront\StoreAssistant;

use App\Ai\Agents\StorefrontAssistant;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;

/**
 * Runs one turn of a storefront chat: trims the remembered history, prompts the scoped assistant, and
 * converts the model's off-topic sentinel into one fixed canned reply. Stateless by design — the browser
 * keeps the short history and sends it back each turn, so guest customers need no account or DB row.
 */
class StoreAssistantService
{
    /** How many prior messages (user + assistant) the assistant remembers. */
    public const HISTORY_LIMIT = 15;

    /** The single, identical reply shown whenever a customer asks for something out of scope. */
    public const OFF_TOPIC_REPLY = "I'm just here to help you buy data on this store — things like our bundles, prices, payment, delivery, and tracking an order. I can't help with that one, but ask me anything about your data purchase!";

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{reply: string}
     */
    public function reply(AssistantContext $context, string $message, array $history = []): array
    {
        // The assistant exposes a developer-API-docs tool and decides for itself when to call it, so no
        // keyword guessing happens here — we just run the turn.
        $response = (new StorefrontAssistant($context, $this->toMessages($history)))
            ->prompt($message);

        $text = trim((string) $response);

        if ($text === '' || str_contains($text, StorefrontAssistant::OFF_TOPIC)) {
            return ['reply' => self::OFF_TOPIC_REPLY];
        }

        return ['reply' => $text];
    }

    /**
     * The quick-start questions shown as tappable chips. Kept generic and store-safe (no platform,
     * URL, or reseller wording) so they work on every storefront.
     *
     * @return list<string>
     */
    public static function presetQuestions(): array
    {
        return [
            'How do I buy a data bundle?',
            'Which networks do you have bundles for?',
            'What are your cheapest bundles?',
            'How do I pay?',
            'How long does delivery take?',
            'How do I track my order?',
            'I bought data but it hasn’t arrived — what do I do?',
            'Can I buy data for someone else’s number?',
            'Do the bundles expire?',
            'How do I contact support?',
        ];
    }

    /**
     * Keep only the most recent HISTORY_LIMIT turns and map them to SDK messages, dropping anything that
     * isn't a well-formed user/assistant line.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @return list<Message>
     */
    private function toMessages(array $history): array
    {
        return collect($history)
            ->filter(fn ($m): bool => is_array($m)
                && in_array($m['role'] ?? null, ['user', 'assistant'], true)
                && is_string($m['content'] ?? null)
                && trim($m['content']) !== '')
            ->map(fn (array $m): array => [
                'role' => $m['role'],
                'content' => Str::limit(trim($m['content']), 2000, ''),
            ])
            ->slice(-self::HISTORY_LIMIT)
            ->map(fn (array $m): Message => new Message($m['role'], $m['content']))
            ->values()
            ->all();
    }
}
