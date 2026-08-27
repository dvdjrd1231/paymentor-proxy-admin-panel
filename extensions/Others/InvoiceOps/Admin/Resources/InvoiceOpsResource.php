<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Resources;

use App\Admin\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\NotificationTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\InvoiceOpsResource\Pages\ListInvoiceOps;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;
use Paymenter\Extensions\Others\InvoiceOps\Support\Drafts;
use Paymenter\Extensions\Others\InvoiceOps\Support\Refunds;

/**
 * The three things the reference's invoice page can do that Paymenter's cannot: **publish**
 * a draft, **send** one of the notices by hand, and **record a refund**.
 *
 * A separate screen rather than tabs on core's invoice page, for the reason now documented
 * three times over: a resource's `table()` and `form()` both replace whatever an extension
 * pushes into them. Core's invoice page keeps everything it already does — items, payments,
 * totals — and this adds the operations beside it.
 */
class InvoiceOpsResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Invoice Operations';

    protected static ?string $modelLabel = 'invoice';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'invoice-operations';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public static function canAct(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.update');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Invoice')
                    ->formatStateUsing(fn (Invoice $record): string => '#' . ($record->number ?: $record->id))
                    ->url(fn (Invoice $record): ?string => InvoiceResource::canEdit($record)
                        ? InvoiceResource::getUrl('edit', ['record' => $record])
                        : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')->label('Customer')->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Drafts::STATUS => 'gray',
                        Invoice::STATUS_PAID => 'success',
                        Refunds::STATUS_REFUNDED => 'warning',
                        Invoice::STATUS_CANCELLED => 'danger',
                        default => 'info',
                    }),

                TextColumn::make('total')->label('Total')->money(fn (Invoice $record) => $record->currency_code),

                // Shown only where it is not zero: a column of blanks reads as "no refunds
                // here", which is the truth, without a column of zeroes claiming attention.
                TextColumn::make('refunded')
                    ->label('Refunded')
                    ->state(fn (Invoice $record): ?string => Refunds::refunded($record) > 0
                        ? number_format(Refunds::refunded($record), 2) . ' ' . $record->currency_code
                        : null)
                    ->placeholder('—')
                    ->color('warning'),

                TextColumn::make('due_at')->label('Due')->date()->sortable(),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Drafts::STATUS => 'Draft',
                    Invoice::STATUS_PENDING => 'Unpaid',
                    Invoice::STATUS_PAID => 'Paid',
                    Refunds::STATUS_REFUNDED => 'Refunded',
                    Invoice::STATUS_CANCELLED => 'Cancelled',
                ]),
            ])
            ->recordActions([
                // ── Publish ──────────────────────────────────────────────────────────
                // The reference has two buttons here, Publish and Publish and Send Email.
                // One action with a checkbox rather than two, because they differ by one
                // decision and two buttons that differ by one decision is how somebody sends
                // an email they meant not to.
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => static::canAct() && $record->status === Drafts::STATUS)
                    ->schema([
                        Checkbox::make('send')
                            ->label('Send the customer the new-invoice email')
                            ->default(true),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        Drafts::publish($record, (bool) ($data['send'] ?? false));

                        Notification::make()
                            ->title('Invoice published')
                            ->body('The customer can now see and pay invoice #' . ($record->number ?: $record->id) . '.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Publish this invoice?')
                    ->modalDescription('Until now the customer could not see it. Publishing makes it visible '
                        . 'and payable, and starts the overdue reminders on its due date.'),

                // ── Send a notice ────────────────────────────────────────────────────
                Action::make('sendEmail')
                    ->label('Send email')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn (Invoice $record): bool => static::canAct() && $record->status !== Drafts::STATUS)
                    ->schema([
                        Select::make('template')
                            ->label('Email')
                            ->options(fn (): array => NotificationTemplate::query()
                                ->where('enabled', true)
                                ->orderBy('key')
                                ->pluck('key', 'key')
                                ->all())
                            ->searchable()
                            ->required()
                            ->helperText('The reference offers this list beside the invoice status. Only '
                                . 'enabled templates are shown — a disabled one would silently send nothing.'),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        $sent = Drafts::send($record, $data['template']);

                        Notification::make()
                            ->title($sent ? 'Email sent' : 'Email not sent')
                            ->body($sent
                                ? $data['template'] . ' sent to ' . $record->user->email . '.'
                                : 'The mail server refused it. The invoice is unchanged — check the email log.')
                            ->status($sent ? 'success' : 'danger')
                            ->send();
                    }),

                // ── Refund ───────────────────────────────────────────────────────────
                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Invoice $record): bool => static::canAct()
                        && $record->status === Invoice::STATUS_PAID)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->placeholder('Leave blank for full refund')
                            ->helperText(fn (Invoice $record): string => 'Invoice total is '
                                . number_format((float) $record->total, 2) . ' ' . $record->currency_code . '.'),

                        Select::make('method')
                            ->label('Refund type')
                            ->options([
                                InvoiceRefund::METHOD_OFFLINE => 'Recorded — refunded outside Paymenter',
                                InvoiceRefund::METHOD_GATEWAY => 'Recorded — refunded in the gateway dashboard',
                            ])
                            ->default(InvoiceRefund::METHOD_OFFLINE)
                            ->required()
                            ->helperText('Both options **record** the refund; neither moves money. Paymenter '
                                . 'has no refund contract for gateways, so refund in Stripe or by transfer '
                                . 'first, then record it here.'),

                        Textarea::make('reason')->label('Reason')->rows(2),

                        Checkbox::make('reverse')
                            ->label('Reverse payment — cancel the services this invoice paid for')
                            ->helperText('The reference\'s "undo automated actions triggered by this '
                                . 'transaction". Off by default: refunding an overpayment should not take '
                                . 'the customer\'s proxy away.'),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        $refund = Refunds::record(
                            $record,
                            $data['amount'] !== null && $data['amount'] !== '' ? (float) $data['amount'] : null,
                            $data['method'],
                            $data['reason'] ?: null,
                            (bool) ($data['reverse'] ?? false),
                            Auth::user(),
                        );

                        Notification::make()
                            ->title('Refund recorded')
                            ->body(number_format((float) $refund->amount, 2) . ' ' . $refund->currency_code
                                . ' against invoice #' . ($record->number ?: $record->id)
                                . ($record->fresh()->status === Refunds::STATUS_REFUNDED
                                    ? ' — the invoice is now marked refunded.'
                                    : ' — a part refund, so the invoice stays paid.'))
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (Invoice $record): string => 'Refund invoice #' . ($record->number ?: $record->id)),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoiceOps::route('/')];
    }

    /** Drafts waiting to be published — an invoice nobody can see is one nobody will pay. */
    public static function getNavigationBadge(): ?string
    {
        $drafts = Invoice::where('status', Drafts::STATUS)->count();

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }
}
