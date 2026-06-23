<?php

declare(strict_types=1);

namespace IMathAS\Engine\Dto;

enum Stype: string
{
    case Template = 'template';
    case Code = 'code';

    public static function fromString(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Template;
        }
        return self::from($value);
    }
}
