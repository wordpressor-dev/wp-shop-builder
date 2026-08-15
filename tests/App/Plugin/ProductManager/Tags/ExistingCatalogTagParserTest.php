<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Tags;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingCatalogTagParser;

final class ExistingCatalogTagParserTest extends TestCase
{
    public function testParsesOnlyTagsThatExistInBothTaxonomies(): void
    {
        $repository = new ExistingCatalogTagParserRepository(
            ['elementor', 'marketplace']
        );
        $parser = new ExistingCatalogTagParser($repository);

        $tags = $parser->parse(
            "elementor|elementor\n"
            . "торговая площадка|marketplace\n"
            . "elementor|elementor"
        );

        self::assertCount(2, $tags);
        self::assertSame('elementor', $tags[0]->slug);
        self::assertSame('marketplace', $tags[1]->slug);
    }

    public function testRejectsUnknownTagInsteadOfCreatingIt(): void
    {
        $parser = new ExistingCatalogTagParser(
            new ExistingCatalogTagParserRepository(['elementor'])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Tag is not present in both product_tag and pa_tags'
        );

        $parser->parse('coupon|coupon');
    }

    public function testRejectsMalformedTagLine(): void
    {
        $parser = new ExistingCatalogTagParser(
            new ExistingCatalogTagParserRepository([])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expected Name|slug.'
        );

        $parser->parse('elementor');
    }
}

final readonly class ExistingCatalogTagParserRepository implements
    CatalogTagRepositoryInterface
{
    /**
     * @param list<string> $slugs
     */
    public function __construct(
        private array $slugs
    ) {
    }

    public function existsInBoth(
        string $name,
        string $slug
    ): bool {
        return in_array($slug, $this->slugs, true);
    }
}
