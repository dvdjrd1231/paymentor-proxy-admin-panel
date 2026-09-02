<?php

namespace Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource\RelationManagers;

use App\Admin\Resources\OrderResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\Affiliates\Models\AffiliateOrder;

class AffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $model = AffiliateOrder::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('affiliate_id')
            ->columns([
                TextColumn::make('order.id')->label('Order'),
                // ->affiliate->earnings is the affiliate's whole-history total, the same
                // number on every row regardless of which order it belongs to — found
                // comparing two orders side by side and seeing identical figures. The
                // order's own ->earnings (defined right next to it on the AffiliateOrder
                // model) is what this column is actually meant to show: what *this*
                // referral earned.
                TextColumn::make('earnings')->formatStateUsing(function (AffiliateOrder $affiliateOrder) {
                    if (count($affiliateOrder->earnings) <= 0) {
                        return null;
                    }

                    return implode(', ', array_map(function ($key, $value) {
                        return "$key: $value";
                    }, array_keys($affiliateOrder->earnings), $affiliateOrder->earnings));
                })->label('Earning'),
                TextColumn::make('order.created_at')
                    ->label('Created At')
                    ->since()
                    ->sortable()
                    ->dateTimeTooltip(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()->url(fn ($record) => OrderResource::getUrl('edit', ['record' => $record->order])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
