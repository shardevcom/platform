<?php

declare(strict_types=1);

$composer = getenv('COMPOSER_BINARY') ?: 'composer';

passthru($composer.' test --working-dir=demo/api', $status);

exit($status);
