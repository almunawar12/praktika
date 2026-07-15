<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case AdminPkl = 'admin-pkl';
    case GuruPembimbing = 'guru-pembimbing';
    case PembimbingIndustri = 'pembimbing-industri';
    case Siswa = 'siswa';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminPkl => 'Admin PKL',
            self::GuruPembimbing => 'Guru Pembimbing',
            self::PembimbingIndustri => 'Pembimbing Industri',
            self::Siswa => 'Siswa',
        };
    }

    /**
     * Landing route after login for this role.
     */
    public function homeRoute(): string
    {
        return 'dashboard';
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
