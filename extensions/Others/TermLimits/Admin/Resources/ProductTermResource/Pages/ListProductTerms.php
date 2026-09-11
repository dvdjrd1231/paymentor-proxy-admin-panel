<?php

namespace Paymenter\Extensions\Others\TermLimits\Admin\Resources\ProductTermResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ProductTermResource;

/** The catalogue, with the term each product runs for. */
class ListProductTerms extends ListRecords
{
    protected static string $resource = ProductTermResource::class;
}
