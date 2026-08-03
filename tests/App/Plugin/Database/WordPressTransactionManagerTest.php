<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Database;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;
use UnexpectedValueException;
use WPShop\App\Plugin\Database\Exception\DatabaseTransactionFailed;
use WPShop\App\Plugin\Database\WordPressTransactionManager;

final class WordPressTransactionManagerTest extends TestCase
{
    public function testCommitsSuccessfulOperationAndReturnsExactResult(): void
    {
        $events = [];
        $operationCalls = 0;
        $result = new stdClass();

        $manager = new WordPressTransactionManager(
            static function (
                string $sql
            ) use (&$events): int {
                $events[] = $sql;

                return 0;
            }
        );

        $actual = $manager->transactional(
            static function () use (
                &$events,
                &$operationCalls,
                $result
            ): stdClass {
                $events[] = 'operation';
                $operationCalls++;

                return $result;
            }
        );

        self::assertSame($result, $actual);
        self::assertSame(1, $operationCalls);

        self::assertSame(
            [
                'START TRANSACTION',
                'operation',
                'COMMIT',
            ],
            $events
        );
    }

    public function testRollsBackFailedOperationAndRethrowsExactFailure(): void
    {
        $events = [];
        $failure = new RuntimeException(
            'Operation failed.'
        );

        $manager = new WordPressTransactionManager(
            static function (
                string $sql
            ) use (&$events): int {
                $events[] = $sql;

                return 0;
            }
        );

        $caught = null;

        try {
            $manager->transactional(
                static function () use (
                    &$events,
                    $failure
                ): never {
                    $events[] = 'operation';

                    throw $failure;
                }
            );
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertSame($failure, $caught);

        self::assertSame(
            [
                'START TRANSACTION',
                'operation',
                'ROLLBACK',
            ],
            $events
        );
    }

    public function testWrapsBeginFailureAndDoesNotRunOperation(): void
    {
        $events = [];
        $operationExecuted = false;

        $nativeFailure = new RuntimeException(
            'Native begin failed.'
        );

        $manager = new WordPressTransactionManager(
            static function (
                string $sql
            ) use (
                &$events,
                $nativeFailure
            ): int {
                $events[] = $sql;

                throw $nativeFailure;
            }
        );

        $caught = null;

        try {
            $manager->transactional(
                static function () use (
                    &$operationExecuted
                ): void {
                    $operationExecuted = true;
                }
            );
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertFalse($operationExecuted);
        self::assertSame(['START TRANSACTION'], $events);

        $transactionFailure = $this->requireTransactionFailure(
            $caught
        );

        self::assertStringContainsString(
            'Database transaction "begin" failed',
            $transactionFailure->getMessage()
        );

        self::assertSame(
            $nativeFailure,
            $transactionFailure->getPrevious()
        );
    }

    public function testTreatsFalseBeginResultAsFailure(): void
    {
        $operationExecuted = false;

        $manager = new WordPressTransactionManager(
            static fn (string $sql): bool => false
        );

        $caught = null;

        try {
            $manager->transactional(
                static function () use (
                    &$operationExecuted
                ): void {
                    $operationExecuted = true;
                }
            );
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertFalse($operationExecuted);

        $transactionFailure = $this->requireTransactionFailure(
            $caught
        );

        self::assertStringContainsString(
            'Database transaction "begin" failed',
            $transactionFailure->getMessage()
        );

        self::assertInstanceOf(
            UnexpectedValueException::class,
            $transactionFailure->getPrevious()
        );
    }

    public function testRollsBackCommitFailure(): void
    {
        $events = [];
        $operationCalls = 0;

        $nativeFailure = new RuntimeException(
            'Native commit failed.'
        );

        $manager = new WordPressTransactionManager(
            static function (
                string $sql
            ) use (
                &$events,
                $nativeFailure
            ): int {
                $events[] = $sql;

                if ($sql === 'COMMIT') {
                    throw $nativeFailure;
                }

                return 0;
            }
        );

        $caught = null;

        try {
            $manager->transactional(
                static function () use (
                    &$events,
                    &$operationCalls
                ): string {
                    $events[] = 'operation';
                    $operationCalls++;

                    return 'result';
                }
            );
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertSame(1, $operationCalls);

        self::assertSame(
            [
                'START TRANSACTION',
                'operation',
                'COMMIT',
                'ROLLBACK',
            ],
            $events
        );

        $transactionFailure = $this->requireTransactionFailure(
            $caught
        );

        self::assertStringContainsString(
            'Database transaction "commit" failed',
            $transactionFailure->getMessage()
        );

        self::assertSame(
            $nativeFailure,
            $transactionFailure->getPrevious()
        );

        self::assertNull(
            $transactionFailure->rollbackFailure()
        );
    }

    public function testReportsRollbackFailureAfterOperationFailure(): void
    {
        $events = [];

        $operationFailure = new RuntimeException(
            'Operation failed.'
        );

        $nativeRollbackFailure = new RuntimeException(
            'Native rollback failed.'
        );

        $manager = new WordPressTransactionManager(
            static function (
                string $sql
            ) use (
                &$events,
                $nativeRollbackFailure
            ): int {
                $events[] = $sql;

                if ($sql === 'ROLLBACK') {
                    throw $nativeRollbackFailure;
                }

                return 0;
            }
        );

        $caught = null;

        try {
            $manager->transactional(
                static function () use (
                    &$events,
                    $operationFailure
                ): never {
                    $events[] = 'operation';

                    throw $operationFailure;
                }
            );
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertSame(
            [
                'START TRANSACTION',
                'operation',
                'ROLLBACK',
            ],
            $events
        );

        $transactionFailure = $this->requireTransactionFailure(
            $caught
        );

        self::assertStringContainsString(
            'rollback failed after "Operation failed."',
            $transactionFailure->getMessage()
        );

        self::assertSame(
            $operationFailure,
            $transactionFailure->getPrevious()
        );

        $rollbackFailure = $this->requireTransactionFailure(
            $transactionFailure->rollbackFailure()
        );

        self::assertSame(
            $nativeRollbackFailure,
            $rollbackFailure->getPrevious()
        );
    }

    public function testReportsRollbackFailureAfterCommitFailure(): void
    {
        $events = [];

        $nativeCommitFailure = new RuntimeException(
            'Native commit failed.'
        );

        $nativeRollbackFailure = new RuntimeException(
            'Native rollback failed.'
        );

        $manager = new WordPressTransactionManager(
            static function (
                string $sql
            ) use (
                &$events,
                $nativeCommitFailure,
                $nativeRollbackFailure
            ): int {
                $events[] = $sql;

                if ($sql === 'COMMIT') {
                    throw $nativeCommitFailure;
                }

                if ($sql === 'ROLLBACK') {
                    throw $nativeRollbackFailure;
                }

                return 0;
            }
        );

        $caught = null;

        try {
            $manager->transactional(
                static function () use (&$events): void {
                    $events[] = 'operation';
                }
            );
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertSame(
            [
                'START TRANSACTION',
                'operation',
                'COMMIT',
                'ROLLBACK',
            ],
            $events
        );

        $transactionFailure = $this->requireTransactionFailure(
            $caught
        );

        self::assertStringContainsString(
            'rollback failed after',
            $transactionFailure->getMessage()
        );

        $commitFailure = $this->requireTransactionFailure(
            $transactionFailure->getPrevious()
        );

        self::assertSame(
            $nativeCommitFailure,
            $commitFailure->getPrevious()
        );

        $rollbackFailure = $this->requireTransactionFailure(
            $transactionFailure->rollbackFailure()
        );

        self::assertSame(
            $nativeRollbackFailure,
            $rollbackFailure->getPrevious()
        );
    }

    private function requireTransactionFailure(
        ?Throwable $failure
    ): DatabaseTransactionFailed {
        self::assertInstanceOf(
            DatabaseTransactionFailed::class,
            $failure
        );

        return $failure;
    }
}
