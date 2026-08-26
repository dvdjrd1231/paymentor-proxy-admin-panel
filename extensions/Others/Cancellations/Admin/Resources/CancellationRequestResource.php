<?php

namespace Paymenter\Extensions\Others\Cancellations\Admin\Resources;

use App\Admin\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCancellation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\Cancellations\Admin\Resources\CancellationRequestResource\Pages\ListCancellationRequests;
use Paymenter\Extensions\Others\Cancellations\Support\Requests;

/**
 * Cancellation requests, with the two answers an administrator actually gives.
 *
 * A second resource over core's model rather than actions added to core's list, because a
 * resource's table cannot be extended from an extension: `Table::configureUsing()` runs
 * inside `Table::make()`, and the resource's own `table()` then calls
 * `->recordActions([...])`, which resets the array before repopulating it. The same trap is
 * why AdminOps' Summary link is a core touchpoint. Core's list stays exactly as it is; the
 * WHMCS menu points here.
 */
class CancellationRequestResource extends Resource
{
    protected static ?string $model = ServiceCancellation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-x-circle';

    protected static ?string $navigationLabel = 'Cancellation Requests';

    protected static ?string $modelLabel = 'cancellation request';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'cancellation-requests';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.service_cancellations.viewAny');
    }

    /** Accepting terminates a live service, so it needs the permission that governs that. */
    public static function canAct(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.services.update');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service_id')
                    ->label('Service')
                    ->formatStateUsing(fn (ServiceCancellation $record): string => '#' . $record->service_id)
                    ->url(fn (ServiceCancellation $record): ?string => $record->service
                        ? ServiceResource::getUrl('edit', ['record' => $record->service_id])
                        : null)
                    ->searchable(),

                TextColumn::make('service.user.email')->label('Customer')->searchable(),
                TextColumn::make('service.product.name')->label('Product')->searchable()->toggleable(),

                TextColumn::make('type')
                    ->label('Requested')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'immediate'
                        ? 'Immediately'
                        : 'At period end')
                    ->color(fn (string $state): string => $state === 'immediate' ? 'danger' : 'gray'),

                TextColumn::make('reason')->label('Reason')->wrap()->limit(80)->placeholder('—'),

                // The whole point of the screen: a request whose service is still running.
                TextColumn::make('service.status')
                    ->label('Service')
                    ->badge()
                    ->color(fn (?string $state): string => $state === Service::STATUS_CANCELLED ? 'gray' : 'warning')
                    ->placeholder('deleted'),

                TextColumn::make('created_at')->label('Asked')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'immediate' => 'Immediately',
                    'end_of_period' => 'At period end',
                ]),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Accept now')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Terminate this service now?')
                    ->modalDescription('The proxies are released back to the panel immediately and any unpaid '
                        . 'invoice for this service is cancelled. This cannot be undone from here.')
                    ->visible(fn (ServiceCancellation $record): bool => static::canAct()
                        && $record->service
                        && $record->service->status !== Service::STATUS_CANCELLED)
                    ->action(function (ServiceCancellation $record): void {
                        Requests::accept($record);

                        Notification::make()
                            ->title('Service terminated')
                            ->body('Service #' . $record->service_id . ' has been cancelled and the panel told '
                                . 'to release it.')
                            ->success()
                            ->send();
                    }),

                Action::make('deny')
                    ->label('Refuse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Refuse this request?')
                    ->modalDescription('The service goes back to renewing as though the request had never been '
                        . 'made. The customer is not told automatically.')
                    ->visible(fn (): bool => static::canAct())
                    ->action(function (ServiceCancellation $record): void {
                        $service = $record->service_id;
                        Requests::deny($record);

                        Notification::make()
                            ->title('Request refused')
                            ->body('Service #' . $service . ' will renew normally again.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListCancellationRequests::route('/')];
    }

    /** Immediate requests whose service is still running — work that is waiting on a human. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = Requests::pendingImmediate()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
