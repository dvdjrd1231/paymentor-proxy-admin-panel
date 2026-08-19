<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbCategoryResource;

class ListKbCategories extends ListRecords
{
    protected static string $resource = KbCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
