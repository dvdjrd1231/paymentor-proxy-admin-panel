<?php

namespace Paymenter\Extensions\Others\BillableItems\Admin\Resources;

use App\Admin\Resources\InvoiceResource;
use App\Models\Currency;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource\Pages\ListBillableItems;
use Paymenter\Extensions\Others\BillableItems\Models\BillableItem;
use Paymenter\Extensions\Others\BillableItems\Support\Items;

/**
 * Billing → Billable Items, with the reference's three views and its bulk invoice action.
 */
class BillableItemResource extends Resource
{
    protected static ?string $model = BillableItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Billable Items';

    protected static ?string $modelLabel = 'billable item';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'billable-items';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.update');
    }

    public static function canEdit($record): bool
    {
        // An invoiced item is a line on an invoice the customer has. Editing it here would
        // change the record and not the invoice, so the two would disagree — and the invoice
        // is the one the customer is holding.
        return static::canCreate() && !$record->isInvoiced();
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Customer')
                ->options(fn (): array => User::query()
                    ->orderBy('email')
                    ->limit(200)
                    ->pluck('email', 'id')
                    ->all())
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where('email', 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck('email', 'id')
                    ->all())
                ->required(),

            Select::make('service_id')
                ->label('Service (optional)')
                ->options(fn (callable $get): array => $get('user_id')
                    ? Service::where('user_id', $get('user_id'))->with('product')->get()
                        ->mapWithKeys(fn (Service $s): array => [$s->id => '#' . $s->id . ' — ' . ($s->product?->name ?? 'product gone')])
                        ->all()
                    : [])
                ->searchable()
                ->helperText('A charge that belongs to one proxy reads very differently on an invoice '
                    . 'from one that belongs to the account.'),

            TextInput::make('description')
                ->required()
                ->maxLength(255)
                ->helperText('This is what the customer reads on the invoice. Write it for them, not for you.'),

            TextInput::make('amount')->label('Amount')->numeric()->required()->minValue(0.01),

            TextInput::make('quantity')
                ->label('Hours / Qty')
                ->numeric()
                ->default(1)
                ->required()
                ->minValue(0.01)
                ->helperText('Kept apart from the amount, as the reference keeps them: "3 hours at 40" is '
                    . 'what you want to see again later, and a single total of 120 loses which was wrong.'),

            Select::make('currency_code')
                ->label('Currency')
                ->options(fn (): array => Currency::pluck('code', 'code')->all())
                ->default(config('settings.default_currency'))
                ->required(),

            Select::make('invoice_action')
                ->label('Invoice action')
                ->options([
                    BillableItem::ACTION_NEXT_INVOICE => "Add to the customer's next invoice",
                    BillableItem::ACTION_IMMEDIATELY => 'Invoice on the next daily run',
                    BillableItem::ACTION_HOLD => "Don't invoice for now",
                ])
                ->default(BillableItem::ACTION_NEXT_INVOICE)
                ->required()
                ->helperText('Riding along on an invoice the customer was getting anyway is usually right: '
                    . 'a small charge on an invoice of its own costs more in fees and attention than it '
                    . 'collects. An item left waiting more than twice the invoice lead time gets one of '
                    . 'its own rather than waiting for ever.'),

            Select::make('recur_every')
                ->label('Recur every')
                ->options([
                    'week' => 'Week',
                    'month' => 'Month',
                    'quarter' => 'Quarter',
                    'year' => 'Year',
                ])
                ->placeholder('Never')
                ->helperText('A recurring item is queued again as a new row each time it is invoiced, so '
                    . 'what was charged in March stays attached to March.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('description')->label('Description')->wrap()->searchable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn (BillableItem $record): string => number_format($record->total(), 2)
                        . ' ' . $record->currency_code)
                    ->description(fn (BillableItem $record): ?string => (float) $record->quantity == 1.0
                        ? null
                        : rtrim(rtrim(number_format((float) $record->quantity, 2), '0'), '.') . ' × '
                            . number_format((float) $record->amount, 2)),

                TextColumn::make('recur_every')->label('Recurs')->placeholder('Never')->badge()->toggleable(),

                TextColumn::make('invoice_action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BillableItem::ACTION_NEXT_INVOICE => 'Next invoice',
                        BillableItem::ACTION_IMMEDIATELY => 'Daily run',
                        default => 'On hold',
                    })
                    ->color(fn (string $state): string => $state === BillableItem::ACTION_HOLD ? 'gray' : 'info'),

                TextColumn::make('invoice_id')
                    ->label('Invoice')
                    ->formatStateUsing(fn (?int $state): string => $state ? '#' . $state : '—')
                    ->url(fn (BillableItem $record): ?string => $record->invoice_id
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice_id])
                        : null)
                    ->placeholder('Uninvoiced'),

                TextColumn::make('created_at')->label('Added')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                // The reference's three views, as filters rather than three menu entries that
                // are the same list.
                Filter::make('uninvoiced')
                    ->label('Uninvoiced')
                    ->query(fn (Builder $query): Builder => $query->whereNull('invoiced_at'))
                    ->default(),

                Filter::make('recurring')
                    ->label('Recurring')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('recur_every')),
            ])
            ->headerActions([
                CreateAction::make()->label('Add New'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('invoiceNow')
                    ->label('Invoice now')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Raises an invoice for this item immediately, rather than waiting for '
                        . 'one the customer was getting anyway.')
                    ->visible(fn (BillableItem $record): bool => static::canCreate() && !$record->isInvoiced())
                    ->action(function (BillableItem $record): void {
                        $invoice = Items::invoice($record->user, collect([$record]));

                        Notification::make()
                            ->title('Invoiced')
                            ->body('Added to invoice #' . ($invoice?->id ?? '?') . '.')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // The reference's "Invoice Selected Items".
                    BulkAction::make('invoiceSelected')
                        ->label('Invoice selected items')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => static::canCreate())
                        ->action(function (Collection $records): void {
                            $invoiced = 0;

                            // Per customer and per currency: Paymenter stores no exchange
                            // rate, so one invoice spanning two currencies would carry a
                            // total that is neither.
                            foreach ($records->groupBy(['user_id', 'currency_code']) as $byCurrency) {
                                foreach ($byCurrency as $items) {
                                    $user = $items->first()->user;

                                    if (!$user) {
                                        continue;
                                    }

                                    Items::invoice($user, $items);
                                    $invoiced += $items->count();
                                }
                            }

                            Notification::make()
                                ->title('Invoiced')
                                ->body($invoiced . ' ' . str('item')->plural($invoiced)
                                    . ' put on invoices, one per customer and currency.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillableItems::route('/'),
        ];
    }

    /** Money written down and not yet charged for. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = BillableItem::whereNull('invoiced_at')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
