<?php

namespace Paymenter\Extensions\Others\TermLimits\Admin\Resources;

use App\Admin\Resources\ProductResource;
use App\Models\NotificationTemplate;
use App\Models\Product;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ProductTermResource\Pages\ListProductTerms;
use Paymenter\Extensions\Others\TermLimits\Models\ProductTerm;
use Paymenter\Extensions\Others\TermLimits\Support\Terms;

/**
 * The reference's **Auto Terminate/Fixed Term** field, one row per product.
 *
 * On WHMCS this is two inputs on the product's Pricing tab. It cannot be there here: a
 * resource's form cannot be extended from an extension for the same reason its table cannot
 * — the resource's own `form()` replaces whatever an extension pushed. So the field gets a
 * screen instead, listing every product with the term it will run for and where that term
 * comes from.
 *
 * That last column is the one worth having, and the reference has no equivalent. Most
 * products here need no setting at all: a daily plan already says one day. Showing
 * **derived** against those, and **set here** against the ones somebody typed, means the
 * screen answers "how long does this product run" for the whole catalogue rather than only
 * for the exceptions.
 */
class ProductTermResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hourglass';

    protected static ?string $navigationLabel = 'Auto Terminate';

    protected static ?string $modelLabel = 'product term';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'auto-terminate';

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.products.viewAny');
    }

    public static function canSet(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.products.update');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->url(fn (Product $record): ?string => ProductResource::canEdit($record)
                        ? ProductResource::getUrl('edit', ['record' => $record])
                        : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')->label('Group')->searchable()->toggleable(),

                TextColumn::make('term')
                    ->label('Runs for')
                    ->state(fn (Product $record): string => static::describe($record))
                    ->badge()
                    ->color(fn (Product $record): string => static::hours($record) === null ? 'gray' : 'success'),

                // The reference has no column like this, and it is the reason to have the
                // screen: it says which products are set by hand and which answer for
                // themselves, for the whole catalogue at once.
                TextColumn::make('source')
                    ->label('From')
                    ->state(function (Product $record): string {
                        $override = ProductTerm::firstWhere('product_id', $record->id);

                        return ($override && $override->days > 0)
                            ? 'Set here — ' . $override->days . ' ' . str('day')->plural($override->days)
                            : 'Derived from the plan';
                    })
                    ->color('gray'),

                TextColumn::make('termination_email')
                    ->label('Termination email')
                    ->state(fn (Product $record): string => ProductTerm::firstWhere('product_id', $record->id)
                        ?->termination_email ?: 'Default (server_terminated)')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('setTerm')
                    ->label('Auto Terminate')
                    ->icon('heroicon-o-hourglass')
                    ->visible(fn (): bool => static::canSet())
                    ->fillForm(fn (Product $record): array => [
                        'days' => ProductTerm::firstWhere('product_id', $record->id)?->days ?? 0,
                        'termination_email' => ProductTerm::firstWhere('product_id', $record->id)?->termination_email,
                    ])
                    ->schema([
                        TextInput::make('days')
                            ->label('Auto Terminate/Fixed Term')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(3650)
                            ->helperText('The number of days after activation to automatically terminate — free '
                                . 'trials, time-limited products. **0 turns it off**, and the term then comes from '
                                . 'the plan instead: a one-time daily plan runs 24 hours, a weekly one 168.'),

                        Select::make('termination_email')
                            ->label('Termination Email')
                            ->options(fn (): array => NotificationTemplate::query()
                                ->orderBy('key')
                                ->pluck('key', 'key')
                                ->all())
                            ->searchable()
                            ->placeholder('Default — server_terminated')
                            ->helperText('Sent when the fixed term comes to an end. A proxy that stops with '
                                . 'nothing said is a support ticket every time, so there is no "send nothing" '
                                . 'option — leaving this empty uses core\'s termination notice.'),
                    ])
                    ->action(function (Product $record, array $data): void {
                        ProductTerm::updateOrCreate(
                            ['product_id' => $record->id],
                            [
                                'days' => (int) $data['days'],
                                'termination_email' => $data['termination_email'] ?: null,
                            ],
                        );

                        Notification::make()
                            ->title('Saved')
                            // Only new services are affected: a term already open was bought
                            // at the old length, and re-timing it after the fact would change
                            // what somebody paid for.
                            ->body($record->name . ' now runs for ' . static::describe($record->fresh())
                                . '. Services already running keep the term they were sold.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (Product $record): string => 'Auto Terminate — ' . $record->name),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ListProductTerms::route('/')];
    }

    /**
     * The term a *new* service of this product would get, in hours.
     *
     * Asked through {@see Terms::length()} on a throwaway unsaved service rather than
     * reimplemented, so this screen can never disagree with what the sweeper actually does.
     */
    private static function hours(Product $product): ?int
    {
        $service = new Service(['product_id' => $product->id]);
        $service->setRelation('product', $product);
        $service->setRelation('plan', $product->plans->first());

        return Terms::length($service);
    }

    private static function describe(Product $product): string
    {
        $hours = static::hours($product);

        if ($hours === null) {
            return 'No fixed term';
        }

        return match (true) {
            $hours % 168 === 0 => ($hours / 168) . ' ' . str('week')->plural($hours / 168),
            $hours % 24 === 0 => ($hours / 24) . ' ' . str('day')->plural($hours / 24),
            default => $hours . ' ' . str('hour')->plural($hours),
        };
    }
}
