<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\PluginHeader;
use WPShop\Publisher\PluginPackageValidation;

final class PluginPackageValidationTest extends TestCase
{
    public function testExposesHeaderAndScore(): void
    {
        $header = new PluginHeader(
            'Example Plugin',
            '1.0.0'
        );

        $validation = new PluginPackageValidation(
            $header,
            100.0
        );

        self::assertSame(
            $header,
            $validation->header()
        );

        self::assertSame(
            100.0,
            $validation->score()
        );
    }

    #[DataProvider('invalidScores')]
    public function testRejectsInvalidScore(float $score): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Plugin package validation score '
                . 'must be between 0 and 100.'
        );

        new PluginPackageValidation(
            new PluginHeader(
                'Example Plugin',
                '1.0.0'
            ),
            $score
        );
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidScores(): iterable
    {
        yield 'negative' => [-0.01];
        yield 'above maximum' => [100.01];
        yield 'infinite' => [INF];
        yield 'not a number' => [NAN];
    }
}
