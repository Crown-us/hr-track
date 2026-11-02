<?php

namespace App\Enums;

enum VacancyStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft', self::Published => 'Dibuka', self::Closed => 'Ditutup'
        };
    }
}
