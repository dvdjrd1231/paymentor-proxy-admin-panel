<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Admin\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
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
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource\Pages;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbArticle;

class KbArticleResource extends Resource
{
    protected static ?string $model = KbArticle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-article-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Knowledgebase';

    protected static ?string $navigationLabel = 'Articles';

    protected static ?string $modelLabel = 'Knowledgebase Article';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')
                ->relationship('category', 'name')
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('title')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                    // Leave a hand-edited slug alone; only track the title while it matches.
                    if (($get('slug') ?? '') !== Str::slug((string) $old)) {
                        return;
                    }
                    $set('slug', Str::slug((string) $state));
                }),
            TextInput::make('slug')->required()->maxLength(255)
                ->helperText('Used in the URL: /knowledgebase/<slug>'),
            TextInput::make('description')->maxLength(255)
                ->helperText('One line shown under the title in the article list.'),
            DateTimePicker::make('published_at')
                ->helperText('Leave empty to keep this a draft. A future date publishes it then.'),
            Toggle::make('is_active')->label('Active')->default(true)
                ->helperText('An article shows in the client area only when it is active and published.'),
            RichEditor::make('content')->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(50),
                TextColumn::make('category.name')->label('Category')->searchable()->sortable(),
                TextColumn::make('views')->sortable(),
                TextColumn::make('published_at')->dateTime()->sortable()
                    ->placeholder('Draft'),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKbArticles::route('/'),
            'create' => Pages\CreateKbArticle::route('/create'),
            'edit' => Pages\EditKbArticle::route('/{record}/edit'),
        ];
    }
}
