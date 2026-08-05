<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Domain\MasterData\Actions\UpdateVendor as UpdateVendorAction;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Vendor;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class EditVendor extends EditRecord
{
    protected static string $resource = VendorResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Vendor) {
            abort(404);
        }

        $values = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:65535'],
        ])->validate();

        return app(UpdateVendorAction::class)->execute(
            VendorResource::authenticatedActor(),
            VendorResource::tenantContext(),
            $record,
            $values['name'],
            $values['vat_number'] ?? null,
            $values['email'] ?? null,
            $values['phone'] ?? null,
            $values['address'] ?? null,
            $record->lock_version,
            app(CorrelationId::class)->value(),
        );
    }
}
