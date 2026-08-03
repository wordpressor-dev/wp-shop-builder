<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\PublisherInterface;
use WPShop\Publisher\Exception\PublisherAlreadyRegistered;
use WPShop\Publisher\Exception\PublisherNotFound;
use WPShop\Publisher\PublicationResult;
use WPShop\Publisher\PublisherRegistry;
use WPShop\Release\Release;

final class PublisherRegistryTest extends TestCase
{
    public function testRegistersAndReturnsExactPublisher(): void
    {
        $registry = new PublisherRegistry();
        $publisher = new PublisherRegistryTestPublisher();

        $registry->register(
            'plugin',
            $publisher
        );

        self::assertSame(
            $publisher,
            $registry->publisherFor('plugin')
        );
    }

    #[DataProvider('emptyBlueprintTypes')]
    public function testRejectsEmptyBlueprintType(
        string $blueprintType
    ): void {
        $registry = new PublisherRegistry();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publisher Blueprint type cannot be empty.'
        );

        $registry->register(
            $blueprintType,
            new PublisherRegistryTestPublisher()
        );
    }

    public function testRejectsDuplicateBlueprintType(): void
    {
        $registry = new PublisherRegistry();
        $publisher = new PublisherRegistryTestPublisher();

        $registry->register(
            'plugin',
            $publisher
        );

        $this->expectException(
            PublisherAlreadyRegistered::class
        );

        $this->expectExceptionMessage(
            'A publisher is already registered for Blueprint type "plugin".'
        );

        $registry->register(
            'plugin',
            new PublisherRegistryTestPublisher()
        );
    }

    public function testUnknownBlueprintTypeProducesTypedException(): void
    {
        $registry = new PublisherRegistry();

        $this->expectException(
            PublisherNotFound::class
        );

        $this->expectExceptionMessage(
            'No publisher is registered for Blueprint type "theme".'
        );

        $registry->publisherFor('theme');
    }

    public function testLookupRejectsEmptyBlueprintType(): void
    {
        $registry = new PublisherRegistry();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publisher Blueprint type cannot be empty.'
        );

        $registry->publisherFor(' ');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyBlueprintTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }
}

final class PublisherRegistryTestPublisher implements
    PublisherInterface
{
    public function publish(
        Blueprint $blueprint,
        Release $release
    ): PublicationResult {
        return new PublicationResult(
            '{}',
            null
        );
    }
}
