<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Credit;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Credit Management (clientscredits.php).
 *
 * Its screen is a log, not a single box: every addition and removal is a dated row with a
 * description and the admin who made it, and the balance is what those rows come to. Ours
 * was one Amount field in a modal — the money moved and nothing recorded why
 * (Leandro, 2026-09-20).
 *
 * Opened in a window of its own, as the reference opens it — its clientssummary.tpl calls
 * `window.open(… 'width=800,height=350,scrollbars=yes')`. A blocker only stops a popup the
 * user did not ask for, and this one is a click; where one refuses anyway, the link falls
 * back to opening here.
 */
class ManageCredits extends Page
{
    protected string $view = 'adminops::pages.manage-credits';

    protected static ?string $slug = 'manage-credits';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public ?int $client = null;

    /** '' | add | remove — which of its two forms is open. */
    #[Url]
    public string $action = '';

    /**
     * Opened in a window of its own, as the reference opens it. The page then closes that
     * window rather than offering a way back into a tab it is not part of.
     */
    #[Url]
    public bool $popup = false;

    public string $entryDate = '';

    public string $description = '';

    public string $amount = '0.00';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.users.update');
    }

    public function getTitle(): string
    {
        return 'Credit Management';
    }

    public function mount(): void
    {
        $this->entryDate = now()->format('m/d/Y');
    }

    public function customer(): ?User
    {
        return $this->client ? User::with('credits')->find($this->client) : null;
    }

    public function currency(): string
    {
        return $this->customer()?->currency_code
            ?: config('settings.default_currency', 'USD');
    }

    public function balance(): float
    {
        return (float) ($this->customer()?->credits
            ->firstWhere('currency_code', $this->currency())?->amount ?? 0);
    }

    /** The log, newest first, as the reference lists it. */
    public function entries()
    {
        if (!$this->client || !\Illuminate\Support\Facades\Schema::hasTable('ext_credit_entries')) {
            return collect();
        }

        return DB::table('ext_credit_entries as e')
            ->leftJoin('users as a', 'a.id', '=', 'e.admin_id')
            ->where('e.user_id', $this->client)
            ->orderByDesc('e.entry_date')->orderByDesc('e.id')
            ->get([
                'e.id', 'e.entry_date', 'e.description', 'e.amount', 'e.currency_code',
                'a.first_name', 'a.last_name', 'a.email',
            ]);
    }

    public function open(string $action): void
    {
        $this->action = in_array($action, ['add', 'remove'], true) ? $action : '';
        $this->description = '';
        $this->amount = '0.00';
        $this->entryDate = now()->format('m/d/Y');
    }

    public function cancel(): void
    {
        $this->action = '';
    }

    /**
     * Record one adjustment and move the balance by it.
     *
     * Both happen together: a log entry without the balance behind it, or a balance change
     * with nothing to explain it, is the failure this screen exists to prevent.
     */
    public function save(): void
    {
        Gate::authorize('has-permission', 'admin.users.update');

        $this->validate([
            'client' => 'required|exists:users,id',
            'entryDate' => 'required|date_format:m/d/Y',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|gt:0',
        ], attributes: ['client' => 'client', 'entryDate' => 'date']);

        $currency = $this->currency();
        $magnitude = round((float) $this->amount, 2);
        $delta = $this->action === 'remove' ? -$magnitude : $magnitude;

        $credit = Credit::firstOrCreate(
            ['user_id' => $this->client, 'currency_code' => $currency],
            ['amount' => 0],
        );

        // Never below zero: core has no notion of spending a negative balance. Removing
        // more than is there empties it rather than going under, and the entry records
        // what actually came off.
        $before = (float) $credit->amount;
        $after = max(0, $before + $delta);
        $applied = round($after - $before, 2);

        if ($applied === 0.0) {
            Notification::make()->title('Nothing to remove')
                ->body('This balance is already empty.')->warning()->send();

            return;
        }

        DB::transaction(function () use ($credit, $after, $applied, $currency): void {
            $credit->amount = $after;
            $credit->save();

            DB::table('ext_credit_entries')->insert([
                'user_id' => $this->client,
                'currency_code' => $currency,
                'entry_date' => \Carbon\Carbon::createFromFormat('m/d/Y', $this->entryDate)->toDateString(),
                'description' => trim($this->description),
                'amount' => $applied,
                'admin_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->action = '';
        $this->description = '';
        $this->amount = '0.00';

        Notification::make()
            ->title($applied > 0 ? 'Credit added' : 'Credit removed')
            ->body('The balance is now ' . number_format($after, 2) . ' ' . $currency . '.')
            ->success()->send();
    }

    /**
     * Remove a log row and take its effect off the balance with it.
     *
     * The reference lets staff delete an entry; leaving the balance where it was would make
     * the log disagree with the money, so the adjustment is reversed at the same time.
     */
    public function deleteEntry(int $id): void
    {
        Gate::authorize('has-permission', 'admin.users.update');

        $row = DB::table('ext_credit_entries')
            ->where('id', $id)->where('user_id', $this->client)->first();

        if (!$row) {
            Notification::make()->title('No such entry')->danger()->send();

            return;
        }

        $credit = Credit::where('user_id', $this->client)
            ->where('currency_code', $row->currency_code)->first();

        DB::transaction(function () use ($row, $credit): void {
            if ($credit) {
                $credit->amount = max(0, (float) $credit->amount - (float) $row->amount);
                $credit->save();
            }

            DB::table('ext_credit_entries')->where('id', $row->id)->delete();
        });

        Notification::make()->title('Entry removed')
            ->body('The balance has been put back by ' . number_format(-(float) $row->amount, 2) . '.')
            ->success()->send();
    }
}
