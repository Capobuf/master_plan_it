<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutSessionScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ordinary_logout_revokes_only_the_current_session_for_later_password_actions_to_handle_other_sessions(): void
    {
        $administrator = $this->administrator();
        $rememberToken = str()->random(60);
        $administrator->setRememberToken($rememberToken);
        $administrator->save();
        $otherSessionId = str()->random(40);
        $currentSessionId = session()->getId();

        DB::table('sessions')->insert([
            'id' => $otherSessionId,
            'user_id' => $administrator->getKey(),
            'ip_address' => '192.0.2.10',
            'user_agent' => 'other-device',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $panel = Filament::getPanel('admin');
        $this->assertNotNull($panel);
        Filament::setCurrentPanel($panel);

        $response = $this->actingAs($administrator)->post($panel->getLogoutUrl());

        $response->assertRedirect($panel->getLoginUrl());
        $this->assertGuest();
        $this->assertNotSame($currentSessionId, session()->getId());
        $this->assertDatabaseHas('sessions', [
            'id' => $otherSessionId,
            'user_id' => $administrator->getKey(),
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $administrator->getKey(),
            'remember_token' => $rememberToken,
        ]);
    }

    private function administrator(): User
    {
        app(PermissionCatalogueSeeder::class)->run();

        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
            'password' => Hash::make('logout-administrator-password'),
        ]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }
}
