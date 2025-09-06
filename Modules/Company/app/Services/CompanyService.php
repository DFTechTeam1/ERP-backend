<?php

namespace Modules\Company\Services;

use App\Enums\Company\ExportImportAreaType;
use Illuminate\Support\Facades\Auth;
use Modules\Company\Repository\ExportImportResultRepository;

class CompanyService {
    private $exportImportRepo;

    public function __construct(ExportImportResultRepository $exportImportRepo)
    {
        $this->exportImportRepo = $exportImportRepo;
    }

    /**
     * Get inbox data content for data table
     * We get data from export_import_results table
     * 
     * @param string $type
     * Type will be 'new_area' or 'old_area'. You can refer this enums in App\Enums\Company\ExportImportAreaType
     * 
     * @return array
     */
    public function getInboxData(string $type = ExportImportAreaType::OldArea->value): array
    {
        try {
            $user = Auth::user();

            $itemsPerPage = request('itemsPerPage') ?? 50;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $where = "user_id = {$user->id} and type = '{$type}'";

            if (!empty($search)) {
                $where .= " and lower(name) LIKE '%{$search}%'";
            }

            $data = $this->exportImportRepo->pagination(
                select: "id,area,description,message,user_id",
                where: $where,
                orderBy: "id desc",
                itemsPerPage: $itemsPerPage,
                page: $page
            );

            $totalData = $this->exportImportRepo->list('id', $where)->count();

            return generalResponse(
                message: "Success",
                data: [
                    'paginated' => $data,
                    'totalData' => $totalData
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function clearInboxData(string $type): array
    {
        $this->exportImportRepo->delete(id: 0, where: "id > 0 and user_id = " . Auth::id() . " and type = '{$type}'");

        return generalResponse(message: "All records has been cleared");
    }
}