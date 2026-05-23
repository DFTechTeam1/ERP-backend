<?php

namespace Modules\Company\Services;

use App\Enums\Company\ExportImportAreaType;
use Illuminate\Support\Facades\Auth;
use Modules\Company\Repository\ExportImportResultRepository;
use Modules\Company\Repository\UserGuideRepository;

class CompanyService
{
    private ExportImportResultRepository $exportImportRepo;

    private UserGuideRepository $userGuideRepo;

    public function __construct(
        ExportImportResultRepository $exportImportRepo,
        UserGuideRepository $userGuideRepo
    ) {
        $this->exportImportRepo = $exportImportRepo;
        $this->userGuideRepo = $userGuideRepo;
    }

    public function listUserGuides() {
        try {
            $guides = $this->userGuideRepo->list(
                select: 'name as title,file_path'
            );

            return generalResponse(
                message: "Success",
                data: $guides->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * The function `uploadGuidance` uploads a file and stores information about it in a repository,
     * handling errors if any occur.
     * 
     * @param array payload The `uploadGuidance` function takes an array `` as a parameter. The
     * `` array should contain the following keys:
     * 
     * @return array a success response with the message 'Guidance uploaded successfully' if the file
     * upload and storage are successful, or an error response with the message 'Failed to upload
     * guidance' if there is an issue with the file upload process. If an exception is caught during
     * the process, the function will return an error response with the exception message.
     */
    public function uploadGuidance(array $payload): array
    {
        try {
            // Upload file
            $uploadedFile = uploadFile('guidance', $payload['file']);

            if (! $uploadedFile) {
                return errorResponse('Failed to upload guidance');
            }

            $this->userGuideRepo->store(data: [
                'name' => $payload['title'],
                'file_path' => asset("storage/guidance/{$uploadedFile}")
            ]);

            return generalResponse(
                message: 'Guidance uploaded successfully',
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get inbox data content for data table
     * We get data from export_import_results table
     *
     * @param  string  $type
     *                        Type will be 'new_area' or 'old_area'. You can refer this enums in App\Enums\Company\ExportImportAreaType
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

            if (! empty($search)) {
                $where .= " and lower(name) LIKE '%{$search}%'";
            }

            $data = $this->exportImportRepo->pagination(
                select: 'id,area,description,message,user_id',
                where: $where,
                orderBy: 'id desc',
                itemsPerPage: $itemsPerPage,
                page: $page
            );

            $totalData = $this->exportImportRepo->list('id', $where)->count();

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $data,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function clearInboxData(string $type): array
    {
        $this->exportImportRepo->delete(id: 0, where: 'id > 0 and user_id = '.Auth::id()." and type = '{$type}'");

        return generalResponse(message: 'All records has been cleared');
    }
}
