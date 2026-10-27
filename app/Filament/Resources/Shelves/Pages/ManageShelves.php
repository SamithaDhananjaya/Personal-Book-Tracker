<?php

namespace App\Filament\Resources\Shelves\Pages;

use App\Filament\Resources\Shelves\ShelfResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShelves extends ManageRecords
{
    protected static string $resource = ShelfResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
