<?php

declare(strict_types=1);

$composer = getenv('COMPOSER_BINARY') ?: 'composer';

passthru($composer.' test --working-dir=demo', $status);

exit($status);
