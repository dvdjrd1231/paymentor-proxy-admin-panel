<?php

namespace Paymenter\Extensions\Others\TicketTools\Admin\Resources;

use App\Models\Ticket;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource\Pages\CreateTicketNote;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource\Pages\EditTicketNote;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource\Pages\ListTicketNotes;
use Paymenter\Extensions\Others\TicketTools\Models\TicketNote;

/**
 * Staff-only internal notes. Never shown in the client theme, so customers can't see
 * them. Notes are stamped with the authoring staff user on create.
 */
class TicketNoteResource extends Resource
{
    protected static ?string $model = TicketNote::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-sticky-note-line';

    protected static ?string $navigationLabel = 'Ticket Notes (internal)';

    protected static ?string $modelLabel = 'Ticket Note';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('ticket_id')
                ->label('Ticket')
                ->options(fn () => Ticket::query()->latest()->limit(200)->get()
                    ->mapWithKeys(fn ($t) => [$t->id => '#' . $t->id . ' — ' . $t->subject])->all())
                ->searchable()
                ->required(),
            Textarea::make('body')->label('Internal note (staff only)')->required()->rows(6)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_id')->label('Ticket')->formatStateUsing(fn ($state) => '#' . $state)->sortable(),
                TextColumn::make('body')->limit(80)->wrap(),
                TextColumn::make('author.name')->label('By')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketNotes::route('/'),
            'create' => CreateTicketNote::route('/create'),
            'edit' => EditTicketNote::route('/{record}/edit'),
        ];
    }
}
