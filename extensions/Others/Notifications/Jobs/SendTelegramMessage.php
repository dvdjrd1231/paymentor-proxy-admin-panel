<?php

namespace Paymenter\Extensions\Others\Notifications\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queued delivery of a single Telegram message via the Bot API.
 *
 * Queue + retry are the whole point of this job: Telegram (or the network) can be
 * briefly unavailable, so delivery is retried with backoff instead of being lost.
 */
class SendTelegramMessage implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry a handful of times with growing backoff. */
    public int $tries = 5;

    /** @return array<int> seconds between retries */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    public function __construct(
        public string $botToken,
        public string $chatId,
        public string $text,
    ) {}

    public function handle(): void
    {
        if ($this->botToken === '' || $this->chatId === '') {
            return;
        }

        $response = Http::asJson()
            ->timeout(15)
            ->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $this->text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if (!$response->successful()) {
            $desc = $response->json('description') ?? ('HTTP ' . $response->status());
            // Throwing makes the queue worker retry per $backoff().
            throw new \RuntimeException('Telegram delivery failed: ' . $desc);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('stack')->error('[Notifications/Telegram] gave up delivering message', [
            'chat_id' => $this->chatId,
            'error' => $e->getMessage(),
        ]);
    }
}
