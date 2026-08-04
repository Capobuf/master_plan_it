<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Domain\Tenancy\Actions\CreateTenant as CreateTenantAction;
use App\Filament\Resources\Tenants\TenantResource;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTenantAction::class)->execute(
            TenantResource::authenticatedActor(),
            $data,
            app(CorrelationId::class)->value(),
        );
    }
}
