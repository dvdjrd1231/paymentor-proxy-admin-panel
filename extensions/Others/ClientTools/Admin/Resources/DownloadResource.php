<?php

namespace Paymenter\Extensions\Others\ClientTools\Admin\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\ClientTools\Admin\Resources\DownloadResource\Pages;
use Paymenter\Extensions\Others\ClientTools\Models\Download;

class DownloadResource extends Resource
{
    protected static ?string $model = Download::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-download-2-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Tools';

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?string $modelLabel = 'Download';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('description')->rows(3)
                ->helperText('Shown under the title on the Downloads page.'),
            TextInput::make('category')->maxLength(255)
                ->helperText('Entries are grouped by this. Leave blank for "General".'),
            TextInput::make('url')->required()->maxLength(255)->url()
                ->helperText('Where the file lives. The client area redirects here.'),
            Toggle::make('requires_login')->label('Customers only')->default(true)
                ->helperText('Off means visitors who are not signed in can see and fetch it.'),
            Toggle::make('is_active')->label('Visible')->default(true),
            TextInput::make('sort')->numeric()->default(0)
                ->helperText('Lower numbers appear first.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('category')->searchable()->sortable()->placeholder('General'),
                TextColumn::make('download_count')->label('Downloads')->sortable(),
                IconColumn::make('requires_login')->label('Customers only')->boolean(),
                IconColumn::make('is_active')->label('Visible')->boolean()->sortable(),
                TextColumn::make('sort')->sortable(),
            ])
            ->defaultSort('sort')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDownloads::route('/'),
            'create' => Pages\CreateDownload::route('/create'),
            'edit' => Pages\EditDownload::route('/{record}/edit'),
        ];
    }
}
