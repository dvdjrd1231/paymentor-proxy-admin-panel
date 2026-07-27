<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource\Pages\CreateCannedResponse;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource\Pages\EditCannedResponse;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource\Pages\ListCannedResponses;
use Paymenter\Extensions\Others\TicketTools\Models\CannedResponse;

class CannedResponseResource extends Resource
{
    protected static ?string $model = CannedResponse::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-chat-quote-line';

    protected static ?string $navigationLabel = 'Canned Responses';

    protected static ?string $modelLabel = 'Canned Response';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('department')->label('Department (optional)')
                ->helperText('Leave blank to make it available for any department.')
                ->maxLength(255)->nullable(),
            Textarea::make('body')->required()->rows(8)->columnSpanFull(),
            Toggle::make('active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('department')->placeholder('Any'),
                ToggleColumn::make('active'),
                TextColumn::make('updated_at')->dateTime()->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCannedResponses::route('/'),
            'create' => CreateCannedResponse::route('/create'),
            'edit' => EditCannedResponse::route('/{record}/edit'),
        ];
    }
}
