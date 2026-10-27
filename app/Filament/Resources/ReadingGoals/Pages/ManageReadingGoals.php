<?php

namespace App\Filament\Resources\ReadingGoals\Pages;

use App\Filament\Resources\ReadingGoals\ReadingGoalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReadingGoals extends ManageRecords
{
    protected static string $resource = ReadingGoalResource::class;

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
