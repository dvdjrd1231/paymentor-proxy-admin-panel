<?php

namespace Paymenter\Extensions\Others\Quotes\Admin\Resources;

use App\Admin\Resources\InvoiceResource;
use App\Models\Currency;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource\Pages\ListQuotes;
use Paymenter\Extensions\Others\Quotes\Models\Quote;
use Paymenter\Extensions\Others\Quotes\Support\Quoting;

/**
 * Billing → Quotes, with the reference's Valid / Expired split and its Create New.
 *
 * A quote is editable only while it is a **draft**. Once sent it is a document the customer
 * is looking at, and changing the price under them is the one thing a quoting system must
 * never do — that is what Duplicate is for.
 */
class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Quotes';

    protected static ?string $modelLabel = 'quote';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'quotes';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.update');
    }

    /** Only a draft. See the class note. */
    public static function canEdit($record): bool
    {
        return static::canCreate() && $record->status === Quote::STATUS_DRAFT;
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
                ->options(fn (): array => User::query()->orderBy('email')->limit(200)->pluck('email', 'id')->all())
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where('email', 'like', "%{$search}%")->limit(50)->pluck('email', 'id')->all())
                ->searchable()
                ->required(),

            TextInput::make('subject')
                ->required()
                ->maxLength(255)
                ->helperText('What the customer sees at the top of it. "IPv6 /48 block, 12 months" beats '
                    . '"Quote 14".'),

            Select::make('currency_code')
                ->label('Currency')
                ->options(fn (): array => Currency::pluck('code', 'code')->all())
                ->default(config('settings.default_currency'))
                ->required(),

            DatePicker::make('valid_until')
                ->label('Valid until')
                ->helperText('Leave empty for an open-ended offer. A quote with no date is never expired '
                    . 'automatically — expiring it because the field is blank would invent a deadline '
                    . 'nobody agreed to.'),

            Repeater::make('items')
                ->label('Lines')
                ->relationship()
                ->reorderable()
                ->orderColumn('sort')
                ->minItems(1)
                ->defaultItems(1)
                ->columns(3)
                ->schema([
                    TextInput::make('description')->required()->maxLength(255)->columnSpan(1),
                    TextInput::make('price')->numeric()->required()->columnSpan(1),
                    TextInput::make('quantity')->numeric()->default(1)->required()->minValue(0.01)->columnSpan(1),
                ])
                ->helperText('These become the invoice lines, word for word, if the customer accepts.'),

            Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->helperText('Shown to the customer under the lines — terms, what is not included, how long '
                    . 'delivery takes.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('subject')->label('Subject')->wrap()->searchable(),
                TextColumn::make('user.email')->label('Customer')->searchable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn (Quote $record): string => number_format($record->total(), 2)
                        . ' ' . $record->currency_code),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Quote $record): string => $record->isLapsed()
                        ? 'Sent — past its date'
                        : ucfirst($state))
                    ->color(fn (string $state, Quote $record): string => match (true) {
                        $state === Quote::STATUS_ACCEPTED => 'success',
                        $state === Quote::STATUS_DECLINED, $state === Quote::STATUS_EXPIRED => 'danger',
                        $record->isLapsed() => 'warning',
                        $state === Quote::STATUS_SENT => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('valid_until')->label('Valid until')->date()->placeholder('No deadline')->sortable(),

                TextColumn::make('invoice_id')
                    ->label('Invoice')
                    ->formatStateUsing(fn (?int $state): string => $state ? '#' . $state : '—')
                    ->url(fn (Quote $record): ?string => $record->invoice_id
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice_id])
                        : null)
                    ->toggleable(),

                TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                // The reference's Valid / Expired split.
                Filter::make('valid')
                    ->label('Valid')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', Quote::STATUS_SENT)
                        ->where(function (Builder $inner): void {
                            $inner->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
                        })),

                SelectFilter::make('status')->options([
                    Quote::STATUS_DRAFT => 'Draft',
                    Quote::STATUS_SENT => 'Sent',
                    Quote::STATUS_ACCEPTED => 'Accepted',
                    Quote::STATUS_DECLINED => 'Declined',
                    Quote::STATUS_EXPIRED => 'Expired',
                ]),
            ])
            ->headerActions([
                CreateAction::make()->label('Create New Quote'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send this quote to the customer?')
                    ->modalDescription('Until now it has been invisible to them. Sending makes it visible in '
                        . 'their portal and emails it, and it can no longer be edited — duplicate it instead.')
                    ->visible(fn (Quote $record): bool => static::canCreate()
                        && $record->status === Quote::STATUS_DRAFT)
                    ->action(function (Quote $record): void {
                        Quoting::send($record);

                        Notification::make()->title('Quote sent')->success()->send();
                    }),

                // The reference lets an administrator accept on the customer's behalf, which
                // is what happens when somebody says yes on the telephone.
                Action::make('accept')
                    ->label('Accept for them')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Accept this quote on the customer\'s behalf?')
                    ->modalDescription('This raises a real invoice for the full amount, exactly as if they had '
                        . 'accepted it themselves. Use it when they have said yes somewhere that is not the portal.')
                    ->visible(fn (Quote $record): bool => static::canCreate() && $record->isOpen())
                    ->action(function (Quote $record): void {
                        $invoice = Quoting::accept($record);

                        Notification::make()
                            ->title($invoice ? 'Accepted — invoice #' . $invoice->id . ' raised' : 'Nothing to accept')
                            ->status($invoice ? 'success' : 'warning')
                            ->send();
                    }),

                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (): bool => static::canCreate())
                    ->action(function (Quote $record): void {
                        $copy = Quoting::duplicate($record);

                        Notification::make()
                            ->title('Duplicated')
                            ->body('Quote #' . $copy->id . ' is a draft copy, ready to edit.')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListQuotes::route('/')];
    }

    /** Quotes out with a customer and not yet answered. */
    public static function getNavigationBadge(): ?string
    {
        $open = Quote::where('status', Quote::STATUS_SENT)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
