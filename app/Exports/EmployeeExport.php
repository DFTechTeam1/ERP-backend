<?php

namespace App\Exports;

use App\Enums\Employee\Religion;
use App\Enums\Employee\Status;
use App\Services\ExcelService;
use App\Services\GeneralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;

class EmployeeExport implements FromView, WithEvents, ShouldAutoSize
{
    use RegistersEventListeners, Exportable;

    private $payload;

    private $generalService;

    public function __construct(array $payload)
    {
        $this->payload = $payload;

        $this->generalService = new GeneralService();
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function view(): View
    {
        // get all data
        $where = "deleted_at IS NULL AND status != " . Status::Deleted->value;

        if ($this->payload['only_new'] == 1) {
            $where .= " AND is_sync_with_talenta = 0";
        }

        if (!empty($this->payload['position_ids'])) {
            $positionIds = collect($this->payload['position_ids'])->map(function ($item) {
                return $this->generalService->getIdFromUid($item, new PositionBackup());
            })->toArray();

            $wherePosition = "(" . implode(',', $positionIds) . ")";
            $where .= " AND position_id IN {$wherePosition}";
        }

        $query = Employee::query();

        if (!empty($where)) {
            $query->whereRaw($where);
        }

        Log::debug('WHERE CHECK', [$where]);

        $query->with([
            'position:id,name,division_id',
            'position.division:id,name',
            'jobLevel:id,name',
            'branch:id,name'
        ]);

        $employees = $query->get();

        $employees = collect($employees)->map(function ($item) {
            // first name
            $exp = explode(' ', $item->name);
            $item['last_name'] = array_pop($exp);
            $item['first_name'] = implode(' ', $exp);

            // religion code
            switch ($item->religion) {
                case Religion::Islam->value:
                    $religionCode = 2;
                    break;

                case Religion::Kristen->value:
                    $religionCode = 3;
                    break;

                case Religion::Khatolik->value:
                    $religionCode = 1;
                    break;

                case Religion::Hindu->value:
                    $religionCode = 5;
                    break;

                case Religion::Budha->value:
                    $religionCode = 4;
                    break;

                case Religion::Konghucu->value:
                    $religionCode = 7;
                    break;
                
                default:
                    $religionCode = 7;
                    break;
            }
            $item['religion_code'] = $religionCode;

            return $item;
        });

        return view('hrd::export-employee', compact('employees'));
    }

    public static function afterSheet(AfterSheet $event)
    {
        $service = new ExcelService();

        $sheet = $event->sheet->getDelegate();
        $service->bulkComment([
            // A1
            ['sheet' => $sheet, 'coordinate' => 'A1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'A1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'A1', 'comment' => 'Wajib diisi & tidak boleh sama', 'bold' => false],
            
            // E1
            ['sheet' => $sheet, 'coordinate' => 'E1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'E1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'E1', 'comment' => 'Wajib diisi & tidak boleh sama', 'bold' => false],

            // C1
            ['sheet' => $sheet, 'coordinate' => 'C1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'C1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'C1', 'comment' => 'Wajib diisi', 'bold' => false],

            // J1
            ['sheet' => $sheet, 'coordinate' => 'J1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'J1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'J1', 'comment' => 'Wajib diisi dengan format yyyy-mm-dd', 'bold' => false],

            // K1
            ['sheet' => $sheet, 'coordinate' => 'K1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'K1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'K1', 'comment' => 'Angka saja tanpa + atau spasi', 'bold' => false],

            // L1
            ['sheet' => $sheet, 'coordinate' => 'L1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'L1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'L1', 'comment' => 'Angka saja tanpa + atau spasi', 'bold' => false],

            // M1
            ['sheet' => $sheet, 'coordinate' => 'M1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'M1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'M1', 'comment' => 'Wajib diisi', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'M1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'M1', 'comment' => "1. untuk Pria", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'M1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'M1', 'comment' => "2. untuk Wanita", 'bold' => false],

            // N1
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => 'Wajib diisi', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "1. untuk Single", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "2. untuk Married", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "3. untuk Widow", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'N1', 'comment' => "4. untuk Widower", 'bold' => false],

            // O1
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => 'Wajib diisi', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "1 : Katolik", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "2 : Islam", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "3: Kristen", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "4 : Buddha", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "5 : Hindu", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "6 : Confucius", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'O1', 'comment' => "7 : Others", 'bold' => false],

            // P1
            ['sheet' => $sheet, 'coordinate' => 'P1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'P1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'P1', 'comment' => 'Wajib diisi', 'bold' => false],

            // Q1
            ['sheet' => $sheet, 'coordinate' => 'Q1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'Q1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'Q1', 'comment' => 'Wajib diisi', 'bold' => false],

            // R1
            ['sheet' => $sheet, 'coordinate' => 'R1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'R1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'R1', 'comment' => 'Wajib diisi', 'bold' => false],

            // S1
            ['sheet' => $sheet, 'coordinate' => 'S1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'S1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'S1', 'comment' => 'Diisi jika menggunakan fiter grade & class', 'bold' => false],

            // U1
            ['sheet' => $sheet, 'coordinate' => 'U1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'U1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'U1', 'comment' => 'Wajib diisi dengan Nama Employement Status (Contoh: Permanent, Probation, Contract)', 'bold' => false],

            // V1
            ['sheet' => $sheet, 'coordinate' => 'V1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'V1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'V1', 'comment' => 'Wajib diisi dengan format yyyy-mm-dd', 'bold' => false],

            // Z1
            ['sheet' => $sheet, 'coordinate' => 'Z1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'Z1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'Z1', 'comment' => 'Diisi dengan format yyyy-mm-dd', 'bold' => false],

            // AA1
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => 'Wajib diisi', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '1:TK0', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '2:TK1', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '3:TK2', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '4:TK3', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '5:K0', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '6:K1', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '7:K2', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AA1', 'comment' => '8:K3', 'bold' => false],

            // AI1
            ['sheet' => $sheet, 'coordinate' => 'AI1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'AI1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AI1', 'comment' => 'Isi dengan angka', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AI1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AI1', 'comment' => "1: Monthly", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AI1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AI1', 'comment' => "2: Daily", 'bold' => false],

            // AQ1
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => 'Wajib diisi', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "0: untuk Not Paid", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "1: untuk Paid by Company", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "2: untuk Paid by Employee", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AQ1', 'comment' => "3: default", 'bold' => false],

            // BD1
            ['sheet' => $sheet, 'coordinate' => 'BD1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'BD1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'BD1', 'comment' => 'Isi dengan angka', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'BD1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'BD1', 'comment' => "1: taxable", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'BD1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'BD1', 'comment' => "2: Non Taxable", 'bold' => false],

            // // AM1
            // ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => 'ERP:', 'bold' => true],
            // ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            // ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => 'Isi dengan angka', 'bold' => false],
            // ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            // ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "0: Not Eligible", 'bold' => false],
            // ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            // ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "1: Eligible", 'bold' => false],

            // AM1
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => 'Isi dengan angka', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "1: A", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "2: B", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "3: AB", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AM1', 'comment' => "4: O", 'bold' => false],

            // AP1
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => 'ERP:', 'bold' => true],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => 'Wajib diisi', 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "0: Pegawai Tetap", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "1: Pegawai Tidak Tetap", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "2: Bukan Pegawai yang Bersifat Berkesinambungan", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "3: Bukan Pegawai yang tidak Bersifat Berkesinambungan", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "4: Ekspatriat", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "5: Ekspatriat Dalam Negri", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "6: Tenaga Ahli yang Bersifat Berkesinambungan", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "7: Tenaga Ahli yang Tidak Bersifat Berkesinambungan", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "8: Dewan Komisaris", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "9: Tenaga Ahli yang Bersifat Berkesinambungan >1 PK", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "10: Tenaga Kerja Lepas", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "\r\n", 'bold' => false],
            ['sheet' => $sheet, 'coordinate' => 'AP1', 'comment' => "11: Bukan Pegawai yang Bersifat Berkesinambungan >1 PK", 'bold' => false],
        ]);

    }
}
