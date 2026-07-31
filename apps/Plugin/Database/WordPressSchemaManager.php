<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database;

use Closure;
use InvalidArgumentException;
use WPShop\App\Plugin\Database\Contracts\SchemaManagerInterface;

final readonly class WordPressSchemaManager implements
    SchemaManagerInterface
{
    /**
     * @param Closure(string): void $applySchema
     */
    public function __construct(
        private string $tablePrefix,
        private string $charsetCollate,
        private Closure $applySchema
    ) {
    }

    public function table(string $name): string
    {
        if (
            preg_match(
                '/^[a-z][a-z0-9_]*$/D',
                $name
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid database table name: %s.',
                    $name
                )
            );
        }

        return $this->tablePrefix . $name;
    }

    public function charsetCollate(): string
    {
        return trim($this->charsetCollate);
    }

    public function apply(string $sql): void
    {
        if (trim($sql) === '') {
            throw new InvalidArgumentException(
                'Database schema SQL cannot be empty.'
            );
        }

        ($this->applySchema)($sql);
    }
}
