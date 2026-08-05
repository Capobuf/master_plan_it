<?php

namespace App\Filament\Resources\PlanningYears\Pages;

use App\Domain\MasterData\Actions\CreatePlanningYear as CreatePlanningYearAction;
use App\Domain\MasterData\Data\CreatePlanningYearData;
use App\Filament\Resources\PlanningYears\PlanningYearResource;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class CreatePlanningYear extends CreateRecord
{
    protected static string $resource = PlanningYearResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $values = Validator::make($data, [
            'year_label' => ['required', 'integer', 'between:1000,9999'],
        ])->validate();

        return app(CreatePlanningYearAction::class)->execute(
            PlanningYearResource::authenticatedActor(),
            PlanningYearResource::tenantContext(),
            new CreatePlanningYearData((int) $values['year_label']),
            app(CorrelationId::class)->value(),
        );
    }
}
