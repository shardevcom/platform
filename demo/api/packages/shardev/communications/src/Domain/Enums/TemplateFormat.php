<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Enums;

enum TemplateFormat: string
{
    case HTML = 'html';
    case TEXT = 'text';

    public function isHtml(): bool
    {
        return $this === self::HTML;
    }
}
