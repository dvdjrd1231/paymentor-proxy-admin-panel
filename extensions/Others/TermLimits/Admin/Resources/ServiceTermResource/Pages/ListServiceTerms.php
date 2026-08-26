<?php

namespace Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource;

/**
 * The list, and nothing else.
 *
 * There is no create page and no edit page on purpose: a term is opened by a service going
 * live and changed only by an extension, which is an action on a row. A hand-written term
 * would be a clock nobody bought.
 */
class ListServiceTerms extends ListRecords
{
    protected static string $resource = ServiceTermResource::class;
}
