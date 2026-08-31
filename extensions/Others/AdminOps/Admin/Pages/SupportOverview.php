<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Support Overview, to its screenshot: the department band, the tile row —
 * Active Tickets, Client Replies, Staff Replies, Tickets Without Reply, Average First
 * Response — and the two charts, Average First Reply Time and Tickets Submitted by Hour.
 *
 * All of it is computed from `tickets` and `ticket_messages` over the last 30 days: a
 * client reply is a message by the ticket's own user after the first, a staff reply is
 * anyone else's, and first response is the gap between the ticket's first message and the
 * first staff message after it.
 */
class SupportOverview extends Page
{
    protected string $view = 'adminops::pages.support-overview';

    protected static ?string $slug = 'support-overview';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public string $dept = '';

    public static function canAccess(): bool
    {
        return TicketResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Support Overview';
    }

    protected function getViewData(): array
    {
        $since = now()->subDays(30);

        $tickets = Ticket::query()
            ->when($this->dept !== '', fn ($q) => $q->where('department', $this->dept))
            ->where('created_at', '>=', $since)
            ->with(['messages' => fn ($q) => $q->orderBy('id')])
            ->get();

        $clientReplies = 0;
        $staffReplies = 0;
        $withoutReply = 0;
        $firstResponseMinutes = [];
        $replyGapByDay = [];
        $byHour = array_fill(0, 24, 0);

        foreach ($tickets as $ticket) {
            $byHour[(int) $ticket->created_at->format('G')]++;

            $messages = $ticket->messages;
            $first = $messages->first();
            $firstStaff = $messages->first(fn ($m) => $m->user_id !== $ticket->user_id && $m->id !== $first?->id);

            foreach ($messages->skip(1) as $message) {
                $message->user_id === $ticket->user_id ? $clientReplies++ : $staffReplies++;
            }

            if ($firstStaff && $first) {
                $minutes = $first->created_at->diffInMinutes($firstStaff->created_at);
                $firstResponseMinutes[] = $minutes;
                $day = $firstStaff->created_at->format('m/d');
                $replyGapByDay[$day][] = $minutes;
            } elseif ($ticket->status !== 'closed') {
                $withoutReply++;
            }
        }

        return [
            'departments' => (array) config('settings.ticket_departments'),
            'tiles' => [
                'Active Tickets' => $tickets->where('status', '!=', 'closed')->count(),
                'Client Replies' => $clientReplies,
                'Staff Replies' => $staffReplies,
                'Tickets Without Reply' => $withoutReply,
                'Average First Response' => $firstResponseMinutes === []
                    ? 'N/A'
                    : self::hours((int) round(array_sum($firstResponseMinutes) / count($firstResponseMinutes))),
            ],
            'replyDays' => collect($replyGapByDay)
                ->map(fn (array $gaps) => (int) round(array_sum($gaps) / count($gaps)))
                ->all(),
            'byHour' => $byHour,
            'hourMax' => max(1, max($byHour)),
        ];
    }

    private static function hours(int $minutes): string
    {
        return $minutes < 60 ? $minutes . 'm' : intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
    }
}
