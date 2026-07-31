<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation\Migrations;

use WPShop\App\Plugin\Database\Contracts\SchemaManagerInterface;
use WPShop\App\Plugin\Installation\Contracts\MigrationInterface;

final readonly class CreateInitialSchema implements
    MigrationInterface
{
    public const VERSION = '0.2.0';

    public const BLUEPRINTS_TABLE = 'wps_blueprints';

    public const RELEASES_TABLE = 'wps_releases';

    public const MANIFESTS_TABLE = 'wps_manifests';

    public function __construct(
        private SchemaManagerInterface $schema
    ) {
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function up(): void
    {
        $this->schema->apply(
            $this->blueprintsSchema()
        );

        $this->schema->apply(
            $this->releasesSchema()
        );

        $this->schema->apply(
            $this->manifestsSchema()
        );
    }

    private function blueprintsSchema(): string
    {
        $sql = <<<'SQL'
CREATE TABLE %s (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
uuid char(36) NOT NULL,
slug varchar(191) NOT NULL,
type varchar(64) NOT NULL,
provider_id bigint(20) unsigned DEFAULT NULL,
developer_id bigint(20) unsigned DEFAULT NULL,
current_release_id bigint(20) unsigned DEFAULT NULL,
state varchar(64) NOT NULL DEFAULT 'draft',
workflow varchar(64) NOT NULL DEFAULT 'default',
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
deleted_at datetime DEFAULT NULL,
PRIMARY KEY  (id),
UNIQUE KEY uuid (uuid),
UNIQUE KEY slug (slug),
KEY type_state (type,state),
KEY provider_id (provider_id),
KEY developer_id (developer_id),
KEY current_release_id (current_release_id),
KEY deleted_at (deleted_at)
) %s;
SQL;

        return sprintf(
            $sql,
            $this->schema->table(
                self::BLUEPRINTS_TABLE
            ),
            $this->schema->charsetCollate()
        );
    }

    private function releasesSchema(): string
    {
        $sql = <<<'SQL'
CREATE TABLE %s (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
blueprint_id bigint(20) unsigned NOT NULL,
version varchar(64) NOT NULL,
status varchar(64) NOT NULL DEFAULT 'draft',
manifest_id bigint(20) unsigned DEFAULT NULL,
published tinyint(1) unsigned NOT NULL DEFAULT 0,
validation_score decimal(5,2) DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY blueprint_version (blueprint_id,version),
KEY blueprint_status (blueprint_id,status),
KEY manifest_id (manifest_id),
KEY published (published)
) %s;
SQL;

        return sprintf(
            $sql,
            $this->schema->table(
                self::RELEASES_TABLE
            ),
            $this->schema->charsetCollate()
        );
    }

    private function manifestsSchema(): string
    {
        $sql = <<<'SQL'
CREATE TABLE %s (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
release_id bigint(20) unsigned NOT NULL,
manifest_json longtext NOT NULL,
manifest_hash char(64) NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY release_id (release_id),
KEY manifest_hash (manifest_hash)
) %s;
SQL;

        return sprintf(
            $sql,
            $this->schema->table(
                self::MANIFESTS_TABLE
            ),
            $this->schema->charsetCollate()
        );
    }
}
