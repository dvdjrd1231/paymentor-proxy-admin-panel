<?php

namespace Paymenter\Extensions\Others\TermLimits\Admin\Resources\ProductTermResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ProductTermResource;

/**
 * The catalogue, with the term each product runs for.
 *
 * No create or edit page: the records are products, which are created and edited on their
 * own core screens. The only thing this resource owns is one field on each of them.
 */
class ListProductTerms extends ListRecords
{
    protected static string $resource = ProductTermResource::class;
}
