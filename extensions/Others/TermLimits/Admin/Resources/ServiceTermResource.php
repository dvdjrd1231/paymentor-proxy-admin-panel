<?php

namespace Paymenter\Extensions\Others\TermLimits\Admin\Resources;

use App\Admin\Resources\ServiceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource\Pages\ListServiceTerms;
use Paymenter\Extensions\Others\TermLimits\Models\ServiceTerm;
use Paymenter\Extensions\Others\TermLimits\Support\Terms;

/**
 * Fixed terms, and the one action that changes one.
 *
 * Read-only apart from **Extend**: a term is a record of what was bought and when it
 * started, and the only legitimate change to it is more time, with a reason. Editing
 * `ends_at` directly would be the same act without the account of why.
 */
class ServiceTermResource extends Resource
{
    protected static ?string $model = ServiceTerm::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Fixed Terms';

    protected static ?string $modelLabel = 'fixed term';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.services.viewAny');
    }

    /** Granting time is a change to what a customer is owed, so it needs update, not view. */
    public static function canExtend(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.services.update');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service_id')
                    ->label('Service')
                    ->formatStateUsing(fn (ServiceTerm $record): string => '#' . $record->service_id)
                    ->url(fn (ServiceTerm $record): ?string => $record->service
                        ? ServiceResource::getUrl('edit', ['record' => $record->service_id])
                        : null)
                    ->searchable(),

                TextColumn::make('service.user.email')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('service.product.name')
                    ->label('Product')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('hours')
                    ->label('Bought')
                    ->formatStateUsing(fn (int $state): string => $state . ' h')
                    ->toggleable(),

                // Extensions are shown separately from what was bought rather than folded
                // into one number: "24 h + 6 h" is a term that had a problem, and that is
                // worth being able to see down a column.
                TextColumn::make('extended')
                    ->label('Extended')
                    ->state(fn (ServiceTerm $record): string => $record->extendedHours() === 0
                        ? '—'
                        : '+' . $record->extendedHours() . ' h')
                    ->toggleable(),

                TextColumn::make('started_at')->label('Started')->dateTime()->sortable()->toggleable(),
                TextColumn::make('ends_at')->label('Ends')->dateTime()->sortable(),

                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->state(fn (ServiceTerm $record): string => $record->remainingForHumans())
                    ->badge()
                    ->color(fn (ServiceTerm $record): string => match (true) {
                        !$record->isOpen() => 'gray',
                        $record->ends_at->isPast() => 'danger',
                        $record->ends_at->diffInHours(now()) < 6 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('outcome')
                    ->label('Ended as')
                    ->placeholder('—')
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('open')
                    ->label('Still running')
                    ->query(fn (Builder $query): Builder => $query->whereNull('ended_at'))
                    ->default(),

                SelectFilter::make('outcome')
                    ->options([
                        ServiceTerm::OUTCOME_TERMINATED => 'Terminated',
                        ServiceTerm::OUTCOME_SUSPENDED => 'Suspended',
                        ServiceTerm::OUTCOME_RELEASED => 'Released',
                    ]),
            ])
            ->recordActions([
                Action::make('extend')
                    ->label('Extend')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (): bool => static::canExtend())
                    ->schema([
                        TextInput::make('hours')
                            ->label('Hours to add')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(24 * 30)
                            ->helperText('Added to the end of the term, not to the time now — an outage that '
                                . 'cost six hours costs six hours wherever in the term it happened.'),

                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->minLength(10)
                            ->rows(3)
                            ->helperText('What justifies it — the maintenance window, the outage, the ticket '
                                . 'number. Kept with the grant and never edited.'),
                    ])
                    ->action(function (ServiceTerm $record, array $data): void {
                        Terms::extend(
                            $record,
                            (int) $data['hours'],
                            trim($data['reason']),
                            Auth::user(),
                        );

                        Notification::make()
                            ->title('Term extended')
                            ->body('Service #' . $record->service_id . ' now ends '
                                . $record->fresh()->ends_at->toDayDateTimeString() . '.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (ServiceTerm $record): string => 'Extend service #' . $record->service_id)
                    ->modalDescription(fn (ServiceTerm $record): string => $record->isOpen()
                        ? 'Currently ends ' . $record->ends_at->toDayDateTimeString() . '.'
                        : 'This term already ended. Adding time past now reopens it — the service itself '
                            . 'still has to be reactivated separately.'),

                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading('Extensions granted')
                    ->modalSubmitAction(false)
                    ->visible(fn (ServiceTerm $record): bool => $record->extensions()->exists())
                    ->modalContent(fn (ServiceTerm $record) => view('termlimits::extensions', [
                        'extensions' => $record->extensions()->with('admin')->latest()->get(),
                    ])),
            ])
            ->defaultSort('ends_at', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceTerms::route('/'),
        ];
    }

    /**
     * Terms whose time is up but whose service is somehow still active — which should be
     * empty at all times, because the sweeper runs every minute. A number here means the
     * scheduler is not running, and that is worth a badge on the menu.
     */
    public static function getNavigationBadge(): ?string
    {
        $overdue = Terms::due()->filter(fn (ServiceTerm $term): bool => Terms::isLive($term))->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
