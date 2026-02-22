<?php

namespace App\Enums;

enum TTLEnum: int
{
    case TwoHours = 7200;
    case OneHour = 3600;
    case SixHours = 21600;

    public function getSeconds(): int
    {
        return $this->value;
    }
}
