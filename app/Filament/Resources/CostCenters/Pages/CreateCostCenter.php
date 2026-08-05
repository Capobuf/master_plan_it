<?php

namespace App\Filament\Resources\CostCenters\Pages;

use App\Domain\MasterData\Actions\CreateCostCenter as CreateCostCenterAction;
use App\Filament\Resources\CostCenters\CostCenterResource;
use App\Models\CostCenter;
use App\Support\Diagnostics\CorrelationId;
use DomainException;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class CreateCostCenter extends CreateRecord
{
    protected static string $resource = CostCenterResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $values = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
        ])->validate();
        $context = CostCenterResource::tenantContext();
        $parent = null;
        if (isset($values['parent_id'])) {
            $parent = CostCenter::query()->where('tenant_id', $context->tenantId)->find($values['parent_id']);
            if (! $parent instanceof CostCenter) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
        }

        return app(CreateCostCenterAction::class)->execute(
            CostCenterResource::authenticatedActor(),
            $context,
            $values['name'],
            $parent,
            app(CorrelationId::class)->value(),
        );
    }
}
