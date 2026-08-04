<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Domain\Tenancy\Actions\UpdateTenant as UpdateTenantAction;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Tenant) {
            throw new LogicException('TenantResource can only update Tenant records.');
        }

        return app(UpdateTenantAction::class)->execute(
            TenantResource::authenticatedActor(),
            $record,
            $data,
            $record->lock_version,
            app(CorrelationId::class)->value(),
        );
    }
}
