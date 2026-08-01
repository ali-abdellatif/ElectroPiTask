<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
