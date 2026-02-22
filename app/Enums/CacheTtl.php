<?php

namespace App\Enums;

enum CacheTtl: int
{
    case Short = 60;        // 1 minute
    case Medium = 600;      // 10 minutes
    case Long = 3600;       // 1 hour
    case HalfDay = 43200;   // 12 hours
    case Day = 86400;       // 1 day
    case Week = 604800;     // 1 week

    public static function getHtmlRenderTtl(): self
    {
        return self::Day;
    }

    public static function getMarkdownConversionTtl(): self
    {
        return self::Week;
    }
}
