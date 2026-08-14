<?php

namespace Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources;

use App\Helpers\ExtensionHelper;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource\Pages\ListProvisioningOperations;
use Paymenter\Extensions\Others\ProvisioningOps\Models\ProvisioningOperation;

/**
 * Admin list of failed provisioning actions, each with a one-click **Retry** that
 * re-runs the real lifecycle call through ExtensionHelper.
 */
class ProvisioningOperationResource extends Resource
{
    protected static ?string $model = ProvisioningOperation::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Services';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-alarm-warning-line';

    protected static ?string $navigationLabel = 'Provisioning';

    protected static ?string $modelLabel = 'Provisioning operation';

    /** Show the number of outstanding failures on the nav item. */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = ProvisioningOperation::where('status', ProvisioningOperation::STATUS_FAILED)->count();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        // Read-only log — records are created by extensions, never by hand.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service_id')
                    ->label('Service')
                    ->url(fn (ProvisioningOperation $record) => $record->service
                        ? \App\Admin\Resources\ServiceResource::getUrl('edit', ['record' => $record->service_id])
                        : null)
                    ->sortable(),
                TextColumn::make('service.user.email')
                    ->label('Customer')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('extension')->sortable(),
                TextColumn::make('action')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state === ProvisioningOperation::STATUS_FAILED ? 'danger' : 'success'),
                TextColumn::make('attempts')->sortable(),
                TextColumn::make('error')
                    ->label('Last error')
                    ->wrap()
                    ->limit(90)
                    ->tooltip(fn (ProvisioningOperation $record) => $record->error),
                TextColumn::make('last_attempt_at')->dateTime()->sortable()->label('Last attempt'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ProvisioningOperation::STATUS_FAILED => 'Failed',
                    ProvisioningOperation::STATUS_SUCCEEDED => 'Resolved',
                ]),
                SelectFilter::make('action')->options([
                    'create' => 'Create',
                    'suspend' => 'Suspend',
                    'unsuspend' => 'Unsuspend',
                    'terminate' => 'Terminate',
                    'upgrade' => 'Upgrade',
                    'callback' => 'Callback',
                ]),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('ri-refresh-line')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (ProvisioningOperation $record) => 'Re-run "' . $record->action . '" against the panel for service #' . $record->service_id . '.')
                    // Only failed lifecycle actions can be retried (a callback cannot).
                    ->visible(fn (ProvisioningOperation $record) => $record->isFailed() && $record->retryMethod() !== null)
                    ->action(function (ProvisioningOperation $record) {
                        static::retry($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_attempt_at', 'desc')
            ->emptyStateHeading('No provisioning failures')
            ->emptyStateDescription('Failed create/suspend/terminate calls against a panel API show up here with a Retry button.');
    }

    /**
     * Re-run the lifecycle call. On success the row is closed by the extension's own
     * success path; on failure the extension records a new attempt, so either way the
     * list stays accurate.
     */
    protected static function retry(ProvisioningOperation $record): void
    {
        $service = $record->service;
        $method = $record->retryMethod();

        if (!$service || !$method) {
            Notification::make()->danger()
                ->title('Cannot retry')
                ->body('The service no longer exists, or this action is not retryable.')
                ->send();

            return;
        }

        try {
            ExtensionHelper::callService($service, $method);

            $record->refresh();
            if ($record->isFailed()) {
                // The extension ran but did not clear the failure — treat as unresolved.
                Notification::make()->warning()
                    ->title('Retry ran, but the operation is still marked failed')
                    ->body($record->error ?? '')
                    ->send();

                return;
            }

            Notification::make()->success()
                ->title('Provisioning retry succeeded')
                ->body('Service #' . $service->id . ' — ' . $record->action . ' completed.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Retry failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProvisioningOperations::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
