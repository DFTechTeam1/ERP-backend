<?php

namespace App\Enums\Employee;

enum EmployeeTaxStatus: int
{
    case PegawaiTetap = 0;
    case PegawaiTidakTetap = 1;
    case BukanPegawaiYangBersifatBerkesinambungan = 2;
    case BukanPegawaiYangTidakBersifat = 3;

    public function label(): string
    {
        return match ($this) {
            self::PegawaiTetap => 'Pegawai Tetap',
            self::PegawaiTidakTetap => 'Pegawai Tidak Tetap',
            self::BukanPegawaiYangBersifatBerkesinambungan => 'Bukan Pegawai yang Bersifat Berkesinambungan',
            self::BukanPegawaiYangTidakBersifat => 'Bukan Pegawai yang Tidak Bersifat'
        };
    }
}
