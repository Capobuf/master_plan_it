<?php

namespace Tests\Feature\Console;

use App\Domain\IdentityAccess\Actions\InvalidateUserSessions;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

class ResetAdministratorPasswordCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $this->removeResidualProtectedAdministrators();
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * The persistent test database may retain protected Administrator rows from
     * interrupted previous runs. The command resets the first protected
     * Administrator, so this test removes any residual row to keep the target
     * deterministic.
     */
    private function removeResidualProtectedAdministrators(): void
    {
        $role = DB::table('roles')
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('name', 'Administrator')
            ->first();

        if ($role === null) {
            return;
        }

        $modelIds = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('tenant_id', 0)
            ->pluck('model_id');

        foreach ($modelIds as $modelId) {
            DB::table('model_has_roles')->where('role_id', $role->id)->where('model_id', $modelId)->delete();
            DB::table('users')->where('id', $modelId)->delete();
        }
    }

    public function test_reset_administrator_password_command_is_registered_interactive_and_invalidates_sessions(): void
    {
        $this->assertTrue(class_exists(InvalidateUserSessions::class), 'T001-013 must provide InvalidateUserSessions.');

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $this->insertSession($administrator, 'admin-reset-session');
        $previousHash = (string) $administrator->password;
        $previousRememberToken = (string) $administrator->remember_token;
        $auditCount = AuditEvent::query()->count();

        $command = $this->artisan('admin:reset-password');
        $command->expectsQuestion('New Administrator password', 'emergency-reset-secret');
        $command->expectsOutputToContain('Administrator password reset');
        $command->assertExitCode(SymfonyCommand::SUCCESS);
        $command->run();

        $administrator->refresh();
        $this->assertTrue(Hash::check('emergency-reset-secret', (string) $administrator->password));
        $this->assertNotSame($previousHash, $administrator->password);
        $this->assertNotSame($previousRememberToken, $administrator->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-reset-session']);
        $this->assertDatabaseCount('audit_events', $auditCount + 1);

        $event = AuditEvent::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('platform.administrator.password-reset', $event->event_type);
        $this->assertSame($administrator->getKey(), $event->actor_user_id);
        $this->assertSame($administrator->name, $event->actor_label);
        $this->assertNull($event->tenant_id);
        $this->assertSame($administrator->getMorphClass(), $event->subject_type);
        $this->assertSame($administrator->getKey(), $event->subject_id);
        $this->assertSame([], $event->properties);
    }

    public function test_reset_administrator_password_command_uses_hidden_input_and_rejects_empty_secret(): void
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $previousHash = (string) $administrator->password;
        $auditCount = AuditEvent::query()->count();

        $command = $this->artisan('admin:reset-password');
        $command->expectsQuestion('New Administrator password', '');
        $command->assertExitCode(SymfonyCommand::FAILURE);
        $command->run();

        $administrator->refresh();
        $this->assertSame($previousHash, $administrator->password);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_reset_administrator_password_command_denies_when_no_protected_administrator_exists(): void
    {
        $auditCount = AuditEvent::query()->count();

        $command = $this->artisan('admin:reset-password');
        $command->expectsQuestion('New Administrator password', 'emergency-reset-secret');
        $command->assertExitCode(SymfonyCommand::FAILURE);
        $command->run();

        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    private function insertSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '192.0.2.10',
            'user_agent' => 'identity-access-test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }
}
