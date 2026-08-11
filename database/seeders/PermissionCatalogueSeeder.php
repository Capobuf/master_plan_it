<?php

namespace Database\Seeders;

use App\Support\Authorization\PermissionCatalogue;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalogue::allAbilities() as $ability) {
            Permission::query()->firstOrCreate(['name' => $ability, 'guard_name' => 'web']);
        }

        $administrator = $this->globalRole('Administrator');
        $editor = $this->globalRole('Editor');
        $viewer = $this->globalRole('Viewer');

        $administrator->syncPermissions(PermissionCatalogue::allAbilities());
        $editor->syncPermissions(PermissionCatalogue::editorAbilities());
        $viewer->syncPermissions(PermissionCatalogue::viewerAbilities());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function globalRole(string $name): Role
    {
        return Role::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);
    }
}
