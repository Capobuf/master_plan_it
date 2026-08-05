<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Domain\MasterData\Actions\CreateVendor as CreateVendorAction;
use App\Filament\Resources\Vendors\VendorResource;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class CreateVendor extends CreateRecord
{
    protected static string $resource = VendorResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $values = Validator::make($data, self::rules())->validate();

        return app(CreateVendorAction::class)->execute(
            VendorResource::authenticatedActor(),
            VendorResource::tenantContext(),
            $values['name'],
            $values['vat_number'] ?? null,
            $values['email'] ?? null,
            $values['phone'] ?? null,
            $values['address'] ?? null,
            app(CorrelationId::class)->value(),
        );
    }

    /** @return array<string, list<string>> */
    private static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
