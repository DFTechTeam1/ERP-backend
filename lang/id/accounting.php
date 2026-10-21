<?php

return [
    'coa' => [
        'locked' => 'Ini adalah akun sistem. :field tidak dapat diubah.',
        'lockedGeneric' => 'Ini adalah akun sistem dan tidak dapat diubah.',
        'hasTransactions' => 'Akun ini sudah memiliki transaksi. :field tidak dapat diubah lagi.',
        'parentNotFound' => 'Akun induk tidak ditemukan.',
        'parentNotHeader' => 'Akun hanya dapat ditempatkan di dalam header, bukan di dalam akun posting lain.',
        'parentTypeMismatch' => 'Akun induk harus memiliki tipe akun yang sama.',
        'reparentTypeMismatch' => 'Grup baru harus memiliki tipe akun yang sama.',
        'reparentOntoPostable' => 'Akun hanya dapat dipindahkan ke dalam header, bukan ke akun posting lain.',
        'reparentCycle' => 'Akun tidak dapat dipindahkan ke dalam dirinya sendiri.',
        'hasBalance' => 'Akun ini masih memiliki saldo. Kosongkan saldo sebelum menonaktifkan.',
        'hasChildren' => 'Pindahkan atau hapus sub-akun terlebih dahulu sebelum menghapus grup ini.',
        'leafToHeaderWithTxns' => 'Akun ini sudah memiliki entri sehingga tidak dapat dijadikan grup header.',
        'reparentWarning' => 'Pengelompokan laporan historis akan bergeser.',
        'saved' => 'Akun disimpan',
        'activated' => 'Akun diaktifkan',
        'deactivated' => 'Akun dinonaktifkan',
        'deleted' => 'Akun dihapus',
    ],
    'field' => [
        'code' => 'Kode akun',
        'account_type' => 'Tipe',
        'account_subtype' => 'Subtipe',
        'parent' => 'Induk',
    ],
];
