<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource;

class ListKbArticles extends ListRecords
{
    protected static string $resource = KbArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
