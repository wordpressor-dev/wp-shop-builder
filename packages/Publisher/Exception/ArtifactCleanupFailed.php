<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;
use Throwable;

final class ArtifactCleanupFailed extends RuntimeException
{
    private function __construct(
        private readonly Throwable $initialFailure,
        private readonly Throwable $cleanupFailure
    ) {
        parent::__construct(
            sprintf(
                'Artifact cleanup failed after publication failure. '
                . 'Initial failure: %s Cleanup failure: %s',
                $initialFailure->getMessage(),
                $cleanupFailure->getMessage()
            ),
            0,
            $initialFailure
        );
    }

    public static function because(
        Throwable $initialFailure,
        Throwable $cleanupFailure
    ): self {
        return new self(
            $initialFailure,
            $cleanupFailure
        );
    }

    public function initialFailure(): Throwable
    {
        return $this->initialFailure;
    }

    public function cleanupFailure(): Throwable
    {
        return $this->cleanupFailure;
    }
}
