<?php

namespace Paymenter\Extensions\Others\PaymentFees\Admin\Resources;

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
use Filament\Schemas\Components\Utilities\Get as FormGet;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource\Pages\CreatePaymentFeeRule;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource\Pages\EditPaymentFeeRule;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource\Pages\ListPaymentFeeRules;
use Paymenter\Extensions\Others\PaymentFees\Models\PaymentFeeRule;

class PaymentFeeRuleResource extends Resource
{
    protected static ?string $model = PaymentFeeRule::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-percent-line';

    protected static ?string $navigationLabel = 'Payment Fee Rules';

    protected static ?string $modelLabel = 'Payment Fee Rule';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),

            Select::make('gateway')
                ->label('Gateway')
                ->options(fn () => Gateway::pluck('name', 'extension'))
                ->placeholder('Any gateway')
                ->nullable(),

            Section::make('Fee')
                ->columns(['sm' => 1, 'md' => 3])
                ->schema([
                    Select::make('fee_type')
                        ->options([
                            'fixed' => 'Fixed',
                            'percent' => 'Percentage',
                            'both' => 'Fixed + Percentage',
                        ])
                        ->default('fixed')
                        ->required()
                        ->live(),
                    TextInput::make('fixed_amount')
                        ->numeric()->default(0)->prefix('amount')
                        ->visible(fn (FormGet $get) => in_array($get('fee_type'), ['fixed', 'both'])),
                    TextInput::make('percent_amount')
                        ->numeric()->default(0)->suffix('%')
                        ->visible(fn (FormGet $get) => in_array($get('fee_type'), ['percent', 'both'])),
                ]),

            Section::make('Rules (leave blank to ignore a scope)')
                ->columns(['sm' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('country')->label('Customer country (ISO-2 or name)')->maxLength(64)->nullable(),
                    TextInput::make('currency_code')->label('Currency code')->maxLength(8)->nullable(),
                    Select::make('product_id')->label('Product')
                        ->options(fn () => Product::pluck('name', 'id'))->searchable()->nullable(),
                    Select::make('customer_type')->label('Customer type')
                        ->options(['individual' => 'Individual', 'business' => 'Business'])->nullable(),
                    TextInput::make('min_amount')->label('Min invoice amount')->numeric()->nullable(),
                    TextInput::make('max_amount')->label('Max invoice amount')->numeric()->nullable(),
                ]),

            Section::make()
                ->columns(['sm' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('priority')->numeric()->default(100)
                        ->helperText('Lower is evaluated first.'),
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
                TextColumn::make('fee_type')->formatStateUsing(fn ($state) => str($state)->title()),
                TextColumn::make('fixed_amount'),
                TextColumn::make('percent_amount')->suffix('%'),
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
            'index' => ListPaymentFeeRules::route('/'),
            'create' => CreatePaymentFeeRule::route('/create'),
            'edit' => EditPaymentFeeRule::route('/{record}/edit'),
        ];
    }
}
