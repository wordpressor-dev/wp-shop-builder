<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Naming;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Naming\ProductTitleVersionMigrationService;

final class ProductTitleVersionMigrationServiceTest extends TestCase
{
    public function testRemovesOnlyExactStoredVersionAndPreservesSlug(): void
    {
        $post = (object) [
            'ID' => 10,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Eduma 5.7.2',
            'post_name' => 'eduma',
        ];
        $writes = [];

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (
            &$post,
            &$writes
        ): mixed {
            if ($name === 'get_post') {
                return clone $post;
            }

            if ($name === 'get_post_meta') {
                return '5.7.2';
            }

            if ($name === 'wp_update_post') {
                $data = (array) ($arguments[0] ?? []);
                $writes[] = $data;
                $post->post_title = (string) ($data['post_title'] ?? '');
                $post->post_name = (string) ($data['post_name'] ?? '');

                return 10;
            }

            return null;
        };

        $result = (new ProductTitleVersionMigrationService(
            $call(...)
        ))->apply([
            'productId' => 10,
            'currentTitle' => 'Eduma 5.7.2',
            'storedVersion' => '5.7.2',
            'recommendedTitle' => 'Eduma',
            'action' => 'REMOVE_VERSION',
        ]);

        self::assertSame('UPDATED', $result['status']);
        self::assertSame('Eduma 5.7.2', $result['oldTitle']);
        self::assertSame('Eduma', $result['newTitle']);
        self::assertSame('eduma', $result['slug']);
        self::assertCount(1, $writes);
        self::assertSame(10, $writes[0]['ID']);
        self::assertSame('Eduma', $writes[0]['post_title']);
        self::assertSame('eduma', $writes[0]['post_name']);
        self::assertArrayNotHasKey('post_content', $writes[0]);
        self::assertArrayNotHasKey('post_excerpt', $writes[0]);
        self::assertArrayNotHasKey('post_status', $writes[0]);
    }

    public function testSkipsWhenTitleChangedAfterAudit(): void
    {
        $writes = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$writes): mixed {
            if ($name === 'get_post') {
                return (object) [
                    'ID' => 20,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'post_title' => 'Eduma Updated Manually 5.7.2',
                    'post_name' => 'eduma',
                ];
            }

            if ($name === 'get_post_meta') {
                return '5.7.2';
            }

            if ($name === 'wp_update_post') {
                $writes[] = $arguments;

                return 20;
            }

            return null;
        };

        $result = (new ProductTitleVersionMigrationService(
            $call(...)
        ))->apply([
            'productId' => 20,
            'currentTitle' => 'Eduma 5.7.2',
            'storedVersion' => '5.7.2',
            'recommendedTitle' => 'Eduma',
            'action' => 'REMOVE_VERSION',
        ]);

        self::assertSame('SKIP', $result['status']);
        self::assertSame([], $writes);
        self::assertStringContainsString(
            'title changed',
            strtolower($result['reason'])
        );
    }

    public function testSkipsWhenStoredVersionChangedAfterAudit(): void
    {
        $writes = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$writes): mixed {
            if ($name === 'get_post') {
                return (object) [
                    'ID' => 30,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'post_title' => 'Eduma 5.7.2',
                    'post_name' => 'eduma',
                ];
            }

            if ($name === 'get_post_meta') {
                return '5.7.3';
            }

            if ($name === 'wp_update_post') {
                $writes[] = $arguments;

                return 30;
            }

            return null;
        };

        $result = (new ProductTitleVersionMigrationService(
            $call(...)
        ))->apply([
            'productId' => 30,
            'currentTitle' => 'Eduma 5.7.2',
            'storedVersion' => '5.7.2',
            'recommendedTitle' => 'Eduma',
            'action' => 'REMOVE_VERSION',
        ]);

        self::assertSame('SKIP', $result['status']);
        self::assertSame([], $writes);
        self::assertStringContainsString(
            'version changed',
            strtolower($result['reason'])
        );
    }
}
