<?php

namespace App\Filament\Resources\PlanningYears\Pages;

use App\Filament\Resources\PlanningYears\PlanningYearResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListPlanningYears extends ListRecords
{
    protected static string $resource = PlanningYearResource::class;

    protected function authorizeAccess(): void
    {
        PlanningYearResource::getEloquentQuery();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create planning year')
                ->authorize(fn (): bool => PlanningYearResource::canCreate())
                ->url(PlanningYearResource::getUrl('create')),
        ];
    }
}
