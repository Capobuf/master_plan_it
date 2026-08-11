<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\IdentityAccess\Actions\InvalidateUserSessions;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

final class ResetAdministratorPasswordCommand extends Command
{
    protected $signature = 'admin:reset-password';

    protected $description = 'Reset the global Administrator password with hidden input and session invalidation';

    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly InvalidateUserSessions $invalidateUserSessions,
        private readonly AuditRecorder $auditRecorder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $secret = (string) $this->secret('New Administrator password');

        if ($secret === '') {
            $this->error('The Administrator password cannot be empty.');

            return SymfonyCommand::FAILURE;
        }

        $administrator = $this->protectedAdministrator();

        if ($administrator === null) {
            $this->error('No protected global Administrator account exists.');

            return SymfonyCommand::FAILURE;
        }

        $correlationId = (string) str()->uuid();

        $reset = function () use ($administrator, $secret, $correlationId): void {
            $administrator->forceFill(['password' => Hash::make($secret)])->save();

            $this->invalidateUserSessions->execute($administrator);

            $this->auditRecorder->record(
                eventType: 'platform.administrator.password-reset',
                correlationId: $correlationId,
                properties: new AuditProperties([]),
                actor: $administrator,
                subject: $administrator,
            );
        };

        if (DB::transactionLevel() > 0) {
            $reset();
        } else {
            DB::transaction($reset);
        }

        $this->info('Administrator password reset.');

        return SymfonyCommand::SUCCESS;
    }

    private function protectedAdministrator(): ?User
    {
        $candidates = User::query()
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->platformAdministrator->hasProtectedRole($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
