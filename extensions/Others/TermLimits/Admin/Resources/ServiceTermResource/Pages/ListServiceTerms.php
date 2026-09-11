<?php

namespace Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource;

/** The list, and nothing else. */
class ListServiceTerms extends ListRecords
{
    protected static string $resource = ServiceTermResource::class;
}
