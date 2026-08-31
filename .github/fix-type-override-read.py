from pathlib import Path

caller = Path('apps/Plugin/ProductManager/WordPress/WordPressFunctionCaller.php')
s = caller.read_text()
needle = """        if ($productId <= 0) {\n            return $result;\n        }\n\n        // Technical type is intentionally independent from the visible catalog\n"""
insert = """        if ($productId <= 0) {\n            return $result;\n        }\n\n        // A deliberate manual technical-type override is authoritative.\n        // Read the marker through the ordinary meta path (different key) so\n        // this branch cannot recurse back into product-type inference.\n        try {\n            $manualOverride = $this->__invoke(\n                'get_post_meta',\n                $productId,\n                '_wp_shop_product_type_manual_override_v1',\n                true\n            );\n            $manualOverride = is_scalar($manualOverride)\n                ? trim((string) $manualOverride)\n                : '';\n            if (in_array($manualOverride, [\n                CatalogProductType::THEME,\n                CatalogProductType::PLUGIN,\n                CatalogProductType::TEMPLATE_KIT,\n            ], true)) {\n                return $manualOverride;\n            }\n        } catch (RuntimeException) {\n        }\n\n        // Technical type is intentionally independent from the visible catalog\n"""
if needle not in s:
    raise SystemExit('WordPressFunctionCaller patch anchor not found')
caller.write_text(s.replace(needle, insert, 1))

service = Path('apps/Plugin/ProductManager/Editorial/ProductEditorialMigrationService.php')
s = service.read_text()
s = s.replace(
    "    private const TYPE_OVERRIDE_BACKUP_META = '_wp_shop_product_type_manual_backup_v1';\n",
    "    private const TYPE_OVERRIDE_BACKUP_META = '_wp_shop_product_type_manual_backup_v1';\n    private const TYPE_OVERRIDE_META = '_wp_shop_product_type_manual_override_v1';\n",
    1,
)
s = s.replace(
    "            $this->meta($productId, 'sales_page'),\n",
    "            $this->meta($productId, 'sales_page'),\n            $this->meta($productId, self::TYPE_OVERRIDE_META),\n",
    1,
)
s = s.replace(
    "                : ($targetType === $editor['storedType'] ? 'CURRENT' : 'READY'),\n",
    "                : ($targetType === $editor['resolvedType'] ? 'CURRENT' : 'READY'),\n",
    1,
)
s = s.replace(
    "                'resolved_type' => $preview['fromType'],\n",
    "                'resolved_type' => $preview['fromType'],\n                'manual_override_type' => $this->meta($productId, self::TYPE_OVERRIDE_META),\n",
    1,
)
s = s.replace(
    "        ($this->call)('update_post_meta', $productId, '_wp_shop_product_type', $targetType);\n\n        $after = $this->technicalTypeEditor($productId);\n",
    "        ($this->call)('update_post_meta', $productId, '_wp_shop_product_type', $targetType);\n        ($this->call)('update_post_meta', $productId, self::TYPE_OVERRIDE_META, $targetType);\n\n        $after = $this->technicalTypeEditor($productId);\n",
    1,
)
s = s.replace(
    "            '_wp_shop_product_type = UPDATED',\n",
    "            '_wp_shop_product_type = UPDATED',\n            'TECHNICAL TYPE MANUAL OVERRIDE = UPDATED',\n",
    1,
)
needle = """            ($this->call)('update_post_meta', $productId, '_wp_shop_product_type', $storedType);\n        }\n\n        $after = $this->technicalTypeEditor($productId);\n"""
repl = """            ($this->call)('update_post_meta', $productId, '_wp_shop_product_type', $storedType);\n        }\n\n        $manualOverride = trim((string) ($backup['manual_override_type'] ?? ''));\n        if ($manualOverride === '') {\n            ($this->call)('delete_post_meta', $productId, self::TYPE_OVERRIDE_META);\n        } else {\n            if (! $this->validTechnicalType($manualOverride)) {\n                throw new RuntimeException(\n                    'Technical type backup contains an unsupported manual override for product '\n                    . $productId\n                );\n            }\n            ($this->call)('update_post_meta', $productId, self::TYPE_OVERRIDE_META, $manualOverride);\n        }\n\n        $after = $this->technicalTypeEditor($productId);\n"""
if needle not in s:
    raise SystemExit('Restore patch anchor not found')
s = s.replace(needle, repl, 1)
needle = """    ): string {\n        $stored = trim($this->meta($productId, '_wp_shop_product_type'));\n"""
repl = """    ): string {\n        $manualOverride = trim($this->meta($productId, self::TYPE_OVERRIDE_META));\n        if ($this->validTechnicalType($manualOverride)) {\n            return $manualOverride;\n        }\n\n        $stored = trim($this->meta($productId, '_wp_shop_product_type'));\n"""
if needle not in s:
    raise SystemExit('productType patch anchor not found')
service.write_text(s.replace(needle, repl, 1))

test = Path('tests/App/Plugin/ProductManager/Editorial/ProductTechnicalTypeOverrideTest.php')
s = test.read_text()
s = s.replace(
    "        self::assertSame('plugin', $meta['_wp_shop_product_type']);\n",
    "        self::assertSame('plugin', $meta['_wp_shop_product_type']);\n        self::assertSame('plugin', $meta['_wp_shop_product_type_manual_override_v1']);\n",
    1,
)
s = s.replace(
    "        self::assertArrayNotHasKey('_wp_shop_product_type', $meta);\n        self::assertSame('theme', $service->technicalTypeEditor(3483)['resolvedType']);\n",
    "        self::assertArrayNotHasKey('_wp_shop_product_type', $meta);\n        self::assertArrayNotHasKey('_wp_shop_product_type_manual_override_v1', $meta);\n        self::assertSame('theme', $service->technicalTypeEditor(3483)['resolvedType']);\n",
    1,
)
test.write_text(s)
