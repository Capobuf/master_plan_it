<?php

namespace App\Filament\Resources\CostCenters\Pages;

use App\Domain\MasterData\Actions\UpdateCostCenter as UpdateCostCenterAction;
use App\Filament\Resources\CostCenters\CostCenterResource;
use App\Models\CostCenter;
use App\Support\Diagnostics\CorrelationId;
use DomainException;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class EditCostCenter extends EditRecord
{
    protected static string $resource = CostCenterResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof CostCenter) {
            abort(404);
        }

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

        return app(UpdateCostCenterAction::class)->execute(
            CostCenterResource::authenticatedActor(),
            $context,
            $record,
            $values['name'],
            $parent,
            $record->lock_version,
            app(CorrelationId::class)->value(),
        );
    }
}
