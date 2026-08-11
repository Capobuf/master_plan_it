<?php

namespace App\Console\Commands;

use App\Domain\Revisions\Actions\ApplyOperationalRevisionRetention;
use Illuminate\Console\Command;

final class ApplyOperationalRevisionRetentionCommand extends Command
{
    protected $signature = 'revisions:apply-retention';

    protected $description = 'Detach redundant package versions beyond the operational history limit.';

    public function handle(ApplyOperationalRevisionRetention $action): int
    {
        $detached = $action->execute();
        $this->info("Detached {$detached} redundant version link(s).");

        return self::SUCCESS;
    }
}
