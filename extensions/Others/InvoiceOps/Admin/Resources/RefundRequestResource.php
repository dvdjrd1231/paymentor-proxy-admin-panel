<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Resources;

use App\Admin\Resources\InvoiceResource;
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
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource\Pages\ListRefundRequests;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;
use Paymenter\Extensions\Others\InvoiceOps\Models\RefundRequest;
use Paymenter\Extensions\Others\InvoiceOps\Support\Requests;

/**
 * Refund requests — the customer asks, an administrator answers.
 *
 * This is the shape that makes refunds workable without a gateway API, and it answers what
 * blocked them. Paymenter cannot push money back through Stripe, but the *decision* needs no
 * API: approve here, refund in the gateway's own dashboard, and the approval writes the
 * record the ledger and the Amount Out column read.
 *
 * Approving is two statements rather than one — how much goes back, and whether the service
 * goes with it. A customer asking for money back is not necessarily asking to lose their
 * proxy, and the two have very different consequences.
 */
class RefundRequestResource extends Resource
{
    protected static ?string $model = RefundRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static ?string $navigationLabel = 'Refund Requests';

    protected static ?string $modelLabel = 'refund request';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'refund-requests';

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
                TextColumn::make('invoice_id')
                    ->label('Invoice')
                    ->formatStateUsing(fn (RefundRequest $record): string => '#'
                        . ($record->invoice?->number ?: $record->invoice_id))
                    ->url(fn (RefundRequest $record): ?string => $record->invoice
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice_id])
                        : null)
                    ->searchable(),

                TextColumn::make('user.email')->label('Customer')->searchable(),

                TextColumn::make('requested')
                    ->label('Asked for')
                    ->state(fn (RefundRequest $record): string => number_format($record->requested(), 2)
                        . ' ' . ($record->invoice?->currency_code ?? '')
                        . ($record->amount === null ? ' (in full)' : ''))
                    ->color('warning'),

                TextColumn::make('reason')->label('Their reason')->wrap()->limit(90),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        RefundRequest::STATUS_APPROVED => 'success',
                        RefundRequest::STATUS_REFUSED => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('created_at')->label('Asked')->dateTime()->sortable(),
                TextColumn::make('admin.name')->label('Answered by')->placeholder('—')->toggleable(),
                TextColumn::make('decision_note')->label('Our reason')->wrap()->limit(80)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        RefundRequest::STATUS_PENDING => 'Waiting',
                        RefundRequest::STATUS_APPROVED => 'Approved',
                        RefundRequest::STATUS_REFUSED => 'Refused',
                    ])
                    ->default(RefundRequest::STATUS_PENDING),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RefundRequest $record): bool => static::canAct() && $record->isPending())
                    ->fillForm(fn (RefundRequest $record): array => [
                        'amount' => $record->amount,
                        'method' => InvoiceRefund::METHOD_GATEWAY,
                    ])
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount to refund')
                            ->numeric()
                            ->minValue(0.01)
                            ->placeholder('Leave blank for the full invoice')
                            ->helperText(fn (RefundRequest $record): string => 'They asked for '
                                . number_format($record->requested(), 2) . ' '
                                . ($record->invoice?->currency_code ?? '')
                                . '. You are not bound to it — a part refund is a legitimate answer.'),

                        Select::make('method')
                            ->label('Where the money went back')
                            ->options([
                                InvoiceRefund::METHOD_GATEWAY => 'Refunded in the gateway dashboard',
                                InvoiceRefund::METHOD_OFFLINE => 'Refunded outside Paymenter',
                            ])
                            ->required()
                            ->helperText('Do the refund first. Approving records what happened; it does not '
                                . 'move money — Paymenter has no refund API for these gateways.'),

                        Textarea::make('note')->label('Note for the record')->rows(2),

                        Checkbox::make('reverse')
                            ->label('Cancel the services this invoice paid for')
                            ->helperText('Off by default. A customer asking for money back is not necessarily '
                                . 'asking to lose their proxy.'),
                    ])
                    ->action(function (RefundRequest $record, array $data): void {
                        Requests::approve(
                            $record,
                            $data['amount'] !== null && $data['amount'] !== '' ? (float) $data['amount'] : null,
                            $data['method'],
                            $data['note'] ?: null,
                            (bool) ($data['reverse'] ?? false),
                            Auth::user(),
                        );

                        Notification::make()
                            ->title('Refund approved and recorded')
                            ->body('Check the money has actually gone back in the gateway — this records the '
                                . 'decision, it does not move funds.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (RefundRequest $record): string => 'Approve refund for invoice #'
                        . ($record->invoice?->number ?: $record->invoice_id)),

                Action::make('refuse')
                    ->label('Refuse')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (RefundRequest $record): bool => static::canAct() && $record->isPending())
                    ->schema([
                        Textarea::make('note')
                            ->label('Why')
                            ->required()
                            ->minLength(10)
                            ->rows(3)
                            ->helperText('Required. A refusal with no reason is the one a customer escalates, '
                                . 'and the one nobody can defend three months later.'),
                    ])
                    ->action(function (RefundRequest $record, array $data): void {
                        Requests::refuse($record, $data['note'], Auth::user());

                        Notification::make()
                            ->title('Request refused')
                            ->body('The reason is on the record. The customer is not told automatically.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRefundRequests::route('/')];
    }

    /** Money a customer has asked for and not been answered on. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = RefundRequest::where('status', RefundRequest::STATUS_PENDING)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
