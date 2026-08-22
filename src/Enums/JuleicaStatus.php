<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Enums;

enum JuleicaStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
}
