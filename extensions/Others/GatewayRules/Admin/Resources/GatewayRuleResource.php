<?php

namespace Paymenter\Extensions\Others\GatewayRules\Admin\Resources;

use App\Models\Category;
use App\Models\Gateway;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource\Pages\CreateGatewayRule;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource\Pages\EditGatewayRule;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource\Pages\ListGatewayRules;
use Paymenter\Extensions\Others\GatewayRules\Models\GatewayRule;

class GatewayRuleResource extends Resource
{
    protected static ?string $model = GatewayRule::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-bank-card-line';

    protected static ?string $navigationLabel = 'Gateway Rules';

    protected static ?string $modelLabel = 'Gateway Rule';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),

            Section::make()
                ->columns(['sm' => 1, 'md' => 2])
                ->schema([
                    Select::make('gateway')
                        ->label('Gateway')
                        ->options(fn () => Gateway::pluck('name', 'extension'))
                        ->placeholder('Any gateway')
                        ->nullable(),
                    Select::make('mode')
                        ->options(['allow' => 'Allow (make available)', 'deny' => 'Deny (hide)'])
                        ->default('deny')
                        ->required(),
                ]),

            Section::make('Conditions (leave blank to ignore a scope)')
                ->columns(['sm' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('country')->label('Customer country (ISO-2 or name)')->maxLength(64)->nullable(),
                    TextInput::make('currency_code')->label('Currency code')->maxLength(8)->nullable(),
                    Select::make('product_id')->label('Product')
                        ->options(fn () => Product::pluck('name', 'id'))->searchable()->nullable(),
                    Select::make('category_id')->label('Product group (category)')
                        ->options(fn () => Category::pluck('name', 'id'))->searchable()->nullable(),
                    Select::make('customer_type')->label('Customer type')
                        ->options(['individual' => 'Individual', 'business' => 'Business'])->nullable(),
                    TextInput::make('min_amount')->label('Min amount')->numeric()->nullable(),
                    TextInput::make('max_amount')->label('Max amount')->numeric()->nullable(),
                ]),

            Section::make()
                ->columns(['sm' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('priority')->numeric()->default(100)->helperText('Lower is evaluated first.'),
                    Toggle::make('active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('gateway')->placeholder('Any'),
                TextColumn::make('mode')->badge()
                    ->color(fn ($state) => $state === 'allow' ? 'success' : 'danger'),
                TextColumn::make('country')->placeholder('—'),
                TextColumn::make('currency_code')->placeholder('—'),
                TextColumn::make('priority')->sortable(),
                ToggleColumn::make('active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGatewayRules::route('/'),
            'create' => CreateGatewayRule::route('/create'),
            'edit' => EditGatewayRule::route('/{record}/edit'),
        ];
    }
}
