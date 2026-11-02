<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Screening = 'screening';
    case Interview = 'interview';
    case Offering = 'offering';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Screening => 'Screening', self::Interview => 'Interview', self::Offering => 'Offering', self::Accepted => 'Diterima', self::Rejected => 'Ditolak'
        };
    }

    public function next(): array
    {
        return match ($this) {
            self::Screening => [self::Interview, self::Rejected],self::Interview => [self::Offering, self::Rejected],self::Offering => [self::Accepted, self::Rejected],default => []
        };
    }
}
