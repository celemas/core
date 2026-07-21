<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap.php';

return $app->run();
