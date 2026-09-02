<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\KnowledgebaseList;
use Paymenter\Extensions\Others\AdminOps\Support\RedirectsToWhmcsPage;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource;

class ListKbArticles extends ListRecords
{
    use RedirectsToWhmcsPage;

    protected static string $resource = KbArticleResource::class;

    protected static function whmcsPageUrl(): string
    {
        return KnowledgebaseList::getUrl();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
