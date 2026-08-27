<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Resources;

use App\Admin\Resources\InvoiceResource;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundResource\Pages\ListRefunds;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;

/**
 * Every refund, in one place — the ledger behind the Refund action.
 *
 * Deliberately **read-only**. A refund is a fact about money that has already moved; the way
 * to correct one is another refund with its own reason, not an edit that quietly rewrites
 * what the books say happened. Same rule as a term extension.
 *
 * The reference has no page quite like this — its refunds live on each invoice — but its
 * Transactions page has an *Amount Out* column that has to come from somewhere, and "show me
 * everything we have given back this month" is a question an invoice-at-a-time view cannot
 * answer.
 */
class RefundResource extends Resource
{
    protected static ?string $model = InvoiceRefund::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Refunds';

    protected static ?string $modelLabel = 'refund';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'refunds';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    /** Nothing here is editable. See the class note. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_id')
                    ->label('Invoice')
                    ->formatStateUsing(fn (InvoiceRefund $record): string => '#'
                        . ($record->invoice?->number ?: $record->invoice_id))
                    ->url(fn (InvoiceRefund $record): ?string => $record->invoice
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice_id])
                        : null)
                    ->searchable(),

                TextColumn::make('invoice.user.email')->label('Customer')->searchable(),

                TextColumn::make('amount')
                    ->label('Refunded')
                    ->formatStateUsing(fn ($state, InvoiceRefund $record): string => number_format((float) $state, 2)
                        . ' ' . $record->currency_code)
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('method')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === InvoiceRefund::METHOD_GATEWAY
                        ? 'In the gateway'
                        : 'Outside Paymenter')
                    ->color('gray'),

                TextColumn::make('reversed_service')
                    ->label('Service reversed')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Cancelled' : 'Kept')
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                    ->toggleable(),

                TextColumn::make('reason')->label('Reason')->wrap()->limit(80)->placeholder('—'),

                TextColumn::make('admin.name')->label('By')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('method')->label('Type')->options([
                    InvoiceRefund::METHOD_OFFLINE => 'Outside Paymenter',
                    InvoiceRefund::METHOD_GATEWAY => 'In the gateway',
                ]),

                SelectFilter::make('reversed_service')
                    ->label('Service')
                    ->options([1 => 'Cancelled with the refund', 0 => 'Kept']),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListRefunds::route('/')];
    }
}
