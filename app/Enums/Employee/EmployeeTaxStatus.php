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
            static::PegawaiTetap => 'Pegawai Tetap',
            static::PegawaiTidakTetap => 'Pegawai Tidak Tetap',
            static::BukanPegawaiYangBersifatBerkesinambungan => 'Bukan Pegawai yang Bersifat Berkesinambungan',
            static::BukanPegawaiYangTidakBersifat => 'Bukan Pegawai yang Tidak Bersifat'
        };
    }

}
