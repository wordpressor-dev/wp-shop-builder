<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database;

use Closure;
use Throwable;
use UnexpectedValueException;
use WPShop\App\Plugin\Database\Contracts\TransactionManagerInterface;
use WPShop\App\Plugin\Database\Exception\DatabaseTransactionFailed;

final readonly class WordPressTransactionManager implements
    TransactionManagerInterface
{
    /**
     * @param Closure(string): (int|bool) $query
     */
    public function __construct(
        private Closure $query
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        $this->execute(
            'START TRANSACTION',
            'begin'
        );

        try {
            $result = $operation();
        } catch (Throwable $failure) {
            $this->rollbackAfter($failure);

            throw $failure;
        }

        try {
            $this->execute(
                'COMMIT',
                'commit'
            );
        } catch (Throwable $failure) {
            $this->rollbackAfter($failure);

            throw $failure;
        }

        return $result;
    }

    private function rollbackAfter(Throwable $failure): void
    {
        try {
            $this->execute(
                'ROLLBACK',
                'rollback'
            );
        } catch (Throwable $rollbackFailure) {
            throw DatabaseTransactionFailed::rollback(
                $failure,
                $rollbackFailure
            );
        }
    }

    private function execute(
        string $sql,
        string $operation
    ): void {
        try {
            $result = ($this->query)($sql);

            if ($result === false) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Database transaction "%s" returned failure.',
                        $operation
                    )
                );
            }
        } catch (Throwable $exception) {
            throw DatabaseTransactionFailed::operation(
                $operation,
                $exception
            );
        }
    }
}
