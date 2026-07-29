<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use WPShop\Core\Kernel\Kernel;

$kernel = new Kernel();
$kernel->boot();

echo "WP Shop Builder Kernel loaded successfully." . PHP_EOL;