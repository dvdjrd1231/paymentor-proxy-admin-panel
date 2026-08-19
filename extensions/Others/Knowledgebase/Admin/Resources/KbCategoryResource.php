<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Admin\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbCategoryResource\Pages;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbCategory;

class KbCategoryResource extends Resource
{
    protected static ?string $model = KbCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-folder-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Knowledgebase';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Knowledgebase Category';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                    // Only auto-fill the slug while it still matches the old name, so a
                    // hand-edited slug is never silently overwritten.
                    if (($get('slug') ?? '') !== Str::slug((string) $old)) {
                        return;
                    }
                    $set('slug', Str::slug((string) $state));
                }),
            TextInput::make('slug')->required()->maxLength(255)
                ->helperText('Used in the URL: /knowledgebase/category/<slug>'),
            TextInput::make('description')->maxLength(255),
            TextInput::make('sort')->numeric()->default(0)
                ->helperText('Lower numbers appear first.'),
            Toggle::make('is_active')->label('Visible')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('articles_count')->counts('articles')->label('Articles'),
                TextColumn::make('sort')->sortable(),
                IconColumn::make('is_active')->label('Visible')->boolean()->sortable(),
            ])
            ->defaultSort('sort')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKbCategories::route('/'),
            'create' => Pages\CreateKbCategory::route('/create'),
            'edit' => Pages\EditKbCategory::route('/{record}/edit'),
        ];
    }
}
