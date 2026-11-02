<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Probation = 'probation';
    case Intern = 'intern';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Tetap', self::Contract => 'Kontrak', self::Probation => 'Percobaan', self::Intern => 'Magang', self::Inactive => 'Nonaktif'
        };
    }
}
