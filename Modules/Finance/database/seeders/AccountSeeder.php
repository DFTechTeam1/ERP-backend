<?php

namespace Modules\Finance\Database\Seeders;

use App\Enums\Accounting\AccountSubtype;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\CashFlowCategory;
use App\Enums\Accounting\NormalBalance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\Account;

/**
 * Chart of Accounts for PT. Citra Bahagia Indonesia, transcribed from
 * docs/coa_dfactory.xlsx.
 *
 * Hierarchy mirrors the worksheet: the 3-digit "x00" / uppercase rows are group
 * headers (is_postable = false), the title-case rows under them are the postable
 * leaf accounts. Codes/names are taken verbatim from the file.
 *
 * account_type is derived from the worksheet section, account_subtype / cash flow
 * mapping is best-effort (see the per-row values) — have Finance review the
 * subtype and cash_flow_category assignments before relying on reports.
 *
 * normal_balance and level are computed on seed; is_postable=false headers get a
 * null normal_balance; contra accounts (accumulated depreciation) flip to credit.
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->accounts() as $node) {
                $this->seedAccount($node, null, 0);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function seedAccount(array $node, ?Account $parent, int $level): void
    {
        $type = $node['accountType'];
        $subtype = $node['accountSubtype'] ?? null;
        $isPostable = $node['isPostable'];

        $account = Account::updateOrCreate(
            ['code' => $node['code']],
            [
                'name' => $node['name'],
                'account_type' => $type,
                'account_subtype' => $subtype,
                'normal_balance' => $isPostable ? $this->deriveNormalBalance($type, $subtype) : null,
                'parent_id' => $parent?->id,
                'level' => $level,
                'is_postable' => $isPostable,
                'is_cash_equivalent' => $node['isCashEquivalent'] ?? false,
                'cash_flow_category' => $node['cashFlowCategory'] ?? null,
                'currency_code' => 'IDR',
                'is_active' => true,
                'is_locked' => false,
            ]
        );

        foreach ($node['children'] ?? [] as $child) {
            $this->seedAccount($child, $account, $level + 1);
        }
    }

    protected function deriveNormalBalance(AccountType $type, ?AccountSubtype $subtype): NormalBalance
    {
        $contra = match ($subtype) {
            AccountSubtype::AccumulatedDepreciation => NormalBalance::Credit,
            AccountSubtype::OwnerDrawing => NormalBalance::Debit,
            default => null,
        };

        if ($contra !== null) {
            return $contra;
        }

        return match ($type) {
            AccountType::Asset, AccountType::Expense => NormalBalance::Debit,
            AccountType::Liability, AccountType::Equity, AccountType::Revenue => NormalBalance::Credit,
        };
    }

    /**
     * Build one header node.
     *
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function header(string $code, string $name, AccountType $type, array $children): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'accountType' => $type,
            'accountSubtype' => null,
            'isPostable' => false,
            'isCashEquivalent' => false,
            'cashFlowCategory' => null,
            'children' => $children,
        ];
    }

    /**
     * Build one postable leaf node.
     *
     * @return array<string, mixed>
     */
    private function leaf(
        string $code,
        string $name,
        AccountType $type,
        ?AccountSubtype $subtype = null,
        ?CashFlowCategory $cashFlow = CashFlowCategory::Operating,
        bool $cashEquivalent = false,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'accountType' => $type,
            'accountSubtype' => $subtype,
            'isPostable' => true,
            'isCashEquivalent' => $cashEquivalent,
            'cashFlowCategory' => $cashFlow,
            'children' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        $asset = AccountType::Asset;
        $liability = AccountType::Liability;
        $equity = AccountType::Equity;
        $revenue = AccountType::Revenue;
        $expense = AccountType::Expense;

        $investing = CashFlowCategory::Investing;
        $financing = CashFlowCategory::Financing;

        return [
            // ---- I. ASET LANCAR (Current Assets) ----
            $this->header('100', 'KAS', $asset, [
                $this->leaf('101', 'Kas', $asset, AccountSubtype::CashAndBank, null, true),
            ]),
            $this->header('110', 'BANK', $asset, [
                $this->leaf('111', 'BCA a/c.', $asset, AccountSubtype::CashAndBank, null, true),
            ]),
            $this->header('130', 'PIUTANG USAHA', $asset, [
                $this->leaf('131', 'Piutang Usaha', $asset, AccountSubtype::AccountsReceivable),
            ]),
            $this->header('140', 'PIUTANG PIHAK LAIN - LAIN', $asset, [
                $this->leaf('141', 'Piutang Karyawan', $asset, AccountSubtype::OtherReceivable),
                $this->leaf('142', 'Piutang Pihak III', $asset, AccountSubtype::OtherReceivable),
            ]),
            $this->header('150', 'PERSEDIAAN', $asset, [
                $this->leaf('151', 'Persediaan Perangkat & Peralatan', $asset, AccountSubtype::Inventory),
            ]),
            $this->header('160', 'UANG MUKA PEMBELIAN', $asset, [
                $this->leaf('161', 'Uang Muka Pembelian Lokal', $asset, AccountSubtype::AdvanceToSupplier),
            ]),
            $this->header('170', 'PAJAK DIBAYAR DIMUKA', $asset, [
                $this->leaf('171', 'PPh Pasal 22 (DDM)', $asset, AccountSubtype::PrepaidExpense),
                $this->leaf('172', 'PPh Pasal 23 (DDM)', $asset, AccountSubtype::PrepaidExpense),
                $this->leaf('173', 'PPh Pasal 25 (DDM)', $asset, AccountSubtype::PrepaidExpense),
            ]),
            $this->header('180', 'BIAYA DIBAYAR DIMUKA', $asset, [
                $this->leaf('181', 'Asuransi Dibayar Dimuka', $asset, AccountSubtype::PrepaidExpense),
                $this->leaf('182', 'Sewa Dibayar Dimuka', $asset, AccountSubtype::PrepaidExpense),
            ]),
            $this->leaf('199', 'AYAT SILANG', $asset, AccountSubtype::OtherCurrentAsset),

            // ---- II. ASET TETAP (Fixed Assets) ----
            $this->leaf('201', 'Tanah', $asset, AccountSubtype::FixedAsset, $investing),
            $this->leaf('202', 'Bangunan', $asset, AccountSubtype::FixedAsset, $investing),
            $this->leaf('211', 'Akumulasi Penyusutan Bangunan', $asset, AccountSubtype::AccumulatedDepreciation, $investing),
            $this->leaf('203', 'Perangkat & Peralatan', $asset, AccountSubtype::FixedAsset, $investing),
            $this->leaf('212', 'Akumulasi Penyusutan Perangkat & Peralatan', $asset, AccountSubtype::AccumulatedDepreciation, $investing),
            $this->leaf('204', 'Inventaris Kantor', $asset, AccountSubtype::FixedAsset, $investing),
            $this->leaf('213', 'Akumulasi Penyusutan Inventaris Kantor', $asset, AccountSubtype::AccumulatedDepreciation, $investing),
            $this->leaf('205', 'Kendaraan', $asset, AccountSubtype::FixedAsset, $investing),
            $this->leaf('214', 'Akumulasi Penyusutan Kendaraan', $asset, AccountSubtype::AccumulatedDepreciation, $investing),
            $this->leaf('206', 'Sarana dan Instalasi', $asset, AccountSubtype::FixedAsset, $investing),
            $this->leaf('215', 'Akumulasi Penyusutan Sarana dan Instalasi', $asset, AccountSubtype::AccumulatedDepreciation, $investing),
            $this->header('220', 'ASET LAIN - LAIN', $asset, [
                $this->leaf('221', 'Aktiva Dalam Penyelesaian', $asset, AccountSubtype::OtherNonCurrentAsset, $investing),
            ]),

            // ---- III. KEWAJIBAN JANGKA PENDEK (Current Liabilities) ----
            $this->header('310', 'HUTANG USAHA', $liability, [
                $this->leaf('311', 'Hutang Usaha', $liability, AccountSubtype::AccountsPayable),
            ]),
            $this->header('320', 'HUTANG LAIN - LAIN', $liability, [
                $this->leaf('321', 'Hutang Pihak III', $liability, AccountSubtype::OtherCurrentLiability),
                $this->leaf('322', 'Hutang Astek', $liability, AccountSubtype::OtherCurrentLiability),
                $this->leaf('323', 'Hutang Direksi', $liability, AccountSubtype::OtherCurrentLiability),
            ]),
            $this->header('330', 'UANG MUKA PENJUALAN', $liability, [
                $this->leaf('331', 'Uang Muka Penjualan', $liability, AccountSubtype::CustomerDeposit),
            ]),
            $this->header('340', 'HUTANG PAJAK', $liability, [
                $this->leaf('341', 'Hutang PPh Pasal 21', $liability, AccountSubtype::TaxPayable),
                $this->leaf('342', 'Hutang PPh Pasal 23/ 26', $liability, AccountSubtype::TaxPayable),
                $this->leaf('343', 'Hutang PPh Pasal 25/ 29', $liability, AccountSubtype::TaxPayable),
                $this->leaf('344', 'Hutang PPh Pasal 4 ayat 2 (Final)', $liability, AccountSubtype::TaxPayable),
                $this->leaf('345', 'PPN-K Yang Belum Difakturkan', $liability, AccountSubtype::TaxPayable),
                $this->leaf('346', 'PPN-K Yang Difakturkan', $liability, AccountSubtype::TaxPayable),
                $this->leaf('347', 'Hutang PPN', $liability, AccountSubtype::TaxPayable),
            ]),
            $this->header('350', 'BIAYA YANG MASIH HARUS DIBAYAR', $liability, [
                $this->leaf('351', 'Hutang Gaji', $liability, AccountSubtype::AccruedExpense),
                $this->leaf('352', 'Hutang Biaya Lainnya', $liability, AccountSubtype::AccruedExpense),
            ]),

            // ---- IV. EKUITAS (Equity) ----
            $this->leaf('401', 'Modal', $equity, AccountSubtype::ShareCapital, $financing),
            $this->leaf('403', 'Saldo (Laba) / Rugi Tahun Lalu', $equity, AccountSubtype::RetainedEarnings, $financing),
            $this->leaf('404', 'Saldo (Laba) / Rugi Tahun Berjalan', $equity, AccountSubtype::CurrentYearEarnings, $financing),
            $this->leaf('405', 'Deviden', $equity, AccountSubtype::OtherEquity, $financing),

            // ---- V. HASIL PENJUALAN (Revenue) ----
            $this->header('500', 'PENDAPATAN', $revenue, [
                $this->leaf('501', 'Pendapatan', $revenue, AccountSubtype::OperatingRevenue),
            ]),

            // ---- Cost of Revenue (HPP / direct costs) ----
            $this->header('530', 'HPP PERANGKAT & PERALATAN', $expense, [
                $this->leaf('531', 'HPP Perangkat & Peralatan', $expense, AccountSubtype::CostOfRevenue),
            ]),
            $this->header('540', 'BIAYA INSTALASI', $expense, [
                $this->leaf('541', 'Biaya Material Instalasi', $expense, AccountSubtype::CostOfRevenue),
            ]),
            $this->header('560', 'BIAYA PEMELIHARAAN', $expense, [
                $this->leaf('561', 'Biaya Pemeliharaan Perangkat & Peralatan', $expense, AccountSubtype::CostOfRevenue),
            ]),
            $this->header('570', 'BIAYA PENYUSUTAN AT', $expense, [
                $this->leaf('571', 'Biaya Penyusutan Perangkat & Peralatan', $expense, AccountSubtype::CostOfRevenue),
                $this->leaf('572', 'Biaya Penyusutan Sarana & Instalasi', $expense, AccountSubtype::CostOfRevenue),
            ]),
            $this->header('580', 'BIAYA SEWA', $expense, [
                $this->leaf('581', 'Biaya Sewa Peralatan & Kendaraan', $expense, AccountSubtype::CostOfRevenue),
                $this->leaf('582', 'Biaya Jasa Lighting , Conceptor', $expense, AccountSubtype::CostOfRevenue),
                $this->leaf('589', 'Sewa/Jasa Lain - lain', $expense, AccountSubtype::CostOfRevenue),
            ]),

            // ---- VI. BIAYA OPERASIONAL / PENJUALAN (Selling Expenses) ----
            $this->header('610', 'BIAYA PENGIRIMAN', $expense, [
                $this->leaf('611', 'Biaya Ekspedisi/ Pengiriman', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('612', 'Biaya Bongkar/ Muat', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('613', 'Bensin/ Tol/ Parkir/ Retribusi', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('614', 'Biaya Sopir/ Kernet', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('620', 'BIAYA PERJALANAN', $expense, [
                $this->leaf('621', 'Biaya Perjalanan Dinas', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('622', 'Biaya Akomodasi', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('630', 'BIAYA PROMOSI', $expense, [
                $this->leaf('631', 'Biaya Iklan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('632', 'Biaya Promosi', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('640', 'BIAYA PEMELIHARAAN AT', $expense, [
                $this->leaf('641', 'Biaya Pemeliharaan Kendaraan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('642', 'Biaya Pengurusan Surat - surat Kendaraan', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('650', 'BIAYA PENYUSUTAN AT', $expense, [
                $this->leaf('651', 'Biaya Penyusutan Kendaraan', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('660', 'BIAYA PENJUALAN LAIN-LAIN', $expense, [
                $this->leaf('661', 'Biaya Komisi Pihak III', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('662', 'Biaya Entertainment', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('663', 'Biaya Penggantian TK Lepas', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('664', 'Biaya Manajemen TK Lepas', $expense, AccountSubtype::OperatingExpense),
            ]),

            // ---- VII. BIAYA UMUM & ADMINISTRASI (General & Admin Expenses) ----
            $this->header('700', 'BIAYA DIREKSI & STAF', $expense, [
                $this->leaf('701', 'Gaji', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('702', 'Tunjangan/ Bonus', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('703', 'Biaya Makan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('704', 'Biaya BPJS ( JKK, JKM, Kesehatan )', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('705', 'BIAYA JHT ( 3,7% ), JP ( 2% )', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('706', 'Iuran Pensiun', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('710', 'BIAYA PERJALANAN', $expense, [
                $this->leaf('711', 'Biaya Perjalanan Direksi/ Staf', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('712', 'Biaya Bensin/ Solar/ Olie', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('713', 'Biaya Akomodasi', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('714', 'Biaya Tol/ Parkir', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('719', 'Biaya Lain - lain', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('720', 'BIAYA KANTOR', $expense, [
                $this->leaf('721', 'Biaya FC/ Cetak/ ATK', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('722', 'Biaya Telkom & Postel', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('723', 'PLN/ PDAM', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('724', 'Biaya Pendidikan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('725', 'Biaya Pengurusan Dokumen/ Surat2', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('726', 'Biaya Jamuan Tamu', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('727', 'Biaya Sewa Bangunan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('728', 'Biaya Kantor Lain-Lain', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('729', 'Biaya Pencegahan Covid 2019', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('730', 'BIAYA PAJAK/ SUMBANGAN/ IURAN', $expense, [
                $this->leaf('731', 'PBB', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('732', 'Sumbangan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('733', 'Iuran', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('734', 'Biaya Pajak', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('740', 'BIAYA OUT SOURCING', $expense, [
                $this->leaf('741', 'Biaya Penggantian Tenaga Kerja Alih Daya (Out Sourcing)', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('742', 'Biaya Jasa Manajemen Alih Daya (Out Sourcing)', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('750', 'BIAYA BANK', $expense, [
                $this->leaf('751', 'Biaya Administrasi Bank', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('752', 'Biaya Provisi', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('760', 'BIAYA PEMELIHARAAN AT', $expense, [
                $this->leaf('761', 'Biaya Pemeliharaan Bangunan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('762', 'Biaya Pemeliharaan Kendaraan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('763', 'Biaya Pemeliharaan Inventaris (Material)', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('763.1', 'Biaya Pemeliharaan Inventaris (Jasa)', $expense, AccountSubtype::OperatingExpense),
            ]),
            $this->header('770', 'BIAYA PENYUSUTAN AT', $expense, [
                $this->leaf('771', 'Biaya Penyusutan Bangunan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('772', 'Biaya Penyusutan Kendaraan', $expense, AccountSubtype::OperatingExpense),
                $this->leaf('773', 'Biaya Penyusutan Inventaris', $expense, AccountSubtype::OperatingExpense),
            ]),

            // ---- VIII. PENGHASILAN / BEBAN LAIN (Other Income / Expense) ----
            $this->header('800', 'P/K LAIN2', $expense, [
                $this->leaf('801', 'Pendapatan Jasa Giro', $revenue, AccountSubtype::OtherIncome),
                $this->leaf('802', 'Bunga Pinjaman Bank', $expense, AccountSubtype::OtherExpense),
                $this->leaf('803', 'Pendapatan Lain-Lain', $revenue, AccountSubtype::OtherIncome),
                $this->leaf('804', 'Biaya Lain-Lain', $expense, AccountSubtype::OtherExpense),
                $this->leaf('805', 'Selisih Kurs', $expense, AccountSubtype::OtherExpense),
                $this->leaf('806', 'Kurang/ Lebih Bayar', $expense, AccountSubtype::OtherExpense),
                $this->leaf('807', 'Potongan Pelunasan Piutang / Pembayaran Hutang', $expense, AccountSubtype::OtherExpense),
            ]),
            $this->leaf('999', 'Pajak Kini', $expense, AccountSubtype::TaxExpense),
        ];
    }
}
