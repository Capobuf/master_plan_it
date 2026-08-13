<?php

namespace App\Domain\Budget\Services;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CoherentBudgetRead
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function execute(Closure $operation): mixed
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            return $operation();
        }

        $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $connection->beginTransaction();

        try {
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollback($connection);

            throw $exception;
        }
    }

    private function rollback(ConnectionInterface $connection): void
    {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
}
