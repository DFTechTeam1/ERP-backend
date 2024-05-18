<?php

namespace Modules\Hrd\Services;

use App\Enums\Employee\ProbationStatus;
use App\Enums\Employee\Status;
use App\Enums\ErrorCode\Code;
use App\Exceptions\EmployeeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;
use Modules\Company\Models\Position;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Repository\EmployeeRepository;

class EmployeeService
{
    private $repo;
    private $positionRepo;
    private $userRepo;

    private $idCardPhotoTmp;
    private $npwpPhotoTmp;
    private $bpjsPhotoTmp;
    private $kkPhotoTmp;

    public function __construct()
    {
        $this->repo = new EmployeeRepository;
        $this->positionRepo = new PositionRepository;

        $this->userRepo = new \App\Repository\UserRepository();
    }

    /**
     * Get list of data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     *
     * @return array
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ) : array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $search = request('search');
            $where = '';
            if (!empty($search)) {
                if (!empty($search['name']) && empty($where)) {
                    $name = $search['name'];
                    $where = "lower(name) LIKE '%{$name}%'";
                } else if (!empty($search['name']) && !empty($where)) {
                    $name = $search['name'];
                    $where .= " AND lower(name) LIKE '%{$name}%'";
                }

                if (!empty($search['employee_id']) && empty($where)) {
                    $employee_id = $search['employee_id'];
                    $where = "lower(employee_id) = '{$employee_id}'";
                } else if (!empty($search['employee_id']) && !empty($where)) {
                    $employee_id = $search['employee_id'];
                    $where .= " AND lower(employee_id) = '{$employee_id}'";
                }

                if (!empty($search['position_id'])) {
                    if (empty($where)) {
                        $where = "position_id IN (";
                    } else if (!empty($where)) {
                        $where .= " AND position_id IN (";
                    }

                    foreach ($search['position_id'] as $positionId) {
                        $posId = getIdFromUid($positionId, new \Modules\Company\Models\Position());
                        $where .= "'{$posId}',";
                    }

                    $where = rtrim($where, ',') . ")";
                }

                if (!empty($search['level_staff'])) {
                    if (empty($where)) {
                        $where = "level_staff IN (";
                    } else if (!empty($where)) {
                        $where .= " AND level_staff IN (";
                    }

                    foreach ($search['level_staff'] as $levelStaff) {
                        $where .= "'{$levelStaff}',";
                    }

                    $where = rtrim($where, ',') . ")";
                }

                if (!empty($search['join_date']) && !empty($search['join_date_condition'])) {
                    $condition = $search['join_date_condition'];
                    if ($condition == 'equal') {
                        $accessor = '=';
                    } else if ($condition == 'less_than') {
                        $accessor = '<=';
                    } else {
                        $accessor = '>=';
                    }

                    if (empty($where)) {
                        $where = "join_date {$accessor} '{$search['join_date']}'";
                    } else if (!empty($where)) {
                        $where .= " AND join_date {$accessor} '{$search['join_date']}'";
                    }
                }

                if (!empty($search['status']) && empty($where)) {
                    $where = "status = {$search['status']}";
                } else if (!empty($search['status']) && !empty($where)) {
                    $where .= " AND status = {$search['status']}";
                }
            }

            $order = '';
            $sortBy = request('sortBy');
            if(!empty($sortBy)) {
                foreach ($sortBy as $item) {
                    if($item['key'] == 'position.name') {
                        $item['key'] = 'position_id';
                    }
                    $orderBy[] = $item['key']." ".$item['order'];
                }
                $order = implode(', ', $orderBy);
            }


            $employees = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $order
            );

            $paginated = collect($employees)->map(function ($item) {
                return [
                    'uid' => $item->uid,
                    'name' => $item->name,
                    'email' => $item->email,
                    'position' => $item->position->name,
                    'level_staff' => __("global.{$item->level_staff}"),
                    'status' => $item->status_text,
                    'status_color' => $item->status_color,
                    'join_date' => date('d F Y', strtotime($item->join_date)),
                    'phone' => $item->phone,
                    'placement' => $item->placement,
                    'employee_id' => $item->employee_id,
                    'user' => $item->user,
                ];
            })->toArray();

            $totalData = $this->repo->list($select, $where, $relation)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'where' => $where,
                ],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Generate new employeeID
     *
     * @return array
     */
    public function generateEmployeeID()
    {
        $latestData = $this->repo->list('id', '', [], 'id DESC');

        $count = count($latestData) == 0 ? 1 : count($latestData) + 1;

        /**
         * DUMMY FORMAT
         * DF010
         *
         */
        $idNumberLength = 3;
        $prefix = 'DF';
        $numbering = $prefix . str_pad($count, $idNumberLength, 0, STR_PAD_LEFT);

        return generalResponse(
            'success',
            false,
            [
                'employee_id' => $numbering,
            ],
        );
    }

    /**
     * Function to get all data
     *
     * @return array
     */
    public function getAll()
    {
        $where = '';
        // $levelStaffOrder = \App\Enums\Employee\LevelStaff::levelStaffOrder();
        $levelStaffOrder = [
            "manager",
            "lead",
            "staff",
            "junior staff"
        ];                             

        $key = request()->min_level;

        if (!empty(request()->min_level)) {
            $search = array_search($key, $levelStaffOrder);

            if ($search > 0) {
                $splice = array_splice($levelStaffOrder, 0, $search);

                $splice = collect($splice)->map(function ($item) {
                    return "'{$item}'";
                })->toArray();
    
                $where = "level_staff IN (". implode(',', $splice) .")";
            }

        }

        $data = $this->repo->list(
            'uid,id,name',
            $where
        );

        $data = collect($data)->map(function ($item) {
            return [
                'value' => $item->uid,
                'title' => $item->name,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $data
        );
    }

    public function activateAccount(string $key)
    {
        $encrypter = new \App\Services\EncryptionService();

        $email = $encrypter->decrypt($key, env('SALT_KEY'));

        $this->userRepo->update([
            'email_verified_at' => \Carbon\Carbon::now(),
        ], 'email', $email);

        return generalResponse(
            __('global.accountIsActive'),
            false,
            [
                'decrypt' => $encrypter->decrypt($key, env('SALT_KEY')),
            ],
        );
    }

    /**
     * Add employee as web app user
     *
     * @param string $id
     * @return array
     */
    public function addAsUser(string $id)
    {
        DB::beginTransaction();
        try {
            $user = $this->repo->show($id, 'id,email,name');

            // generate random password
            $password = generateRandomPassword();
            $userId = $this->userRepo->store([
                'email' => $user->email,
                'password' => $password,
            ]);

            $this->repo->update([
                'user_id' => $userId->id
            ], $id);

            \Modules\Hrd\Jobs\SendEmailActivationJob::dispatch($password, $userId)->afterCommit();

            DB::commit();

            return generalResponse(
                __('global.successAddEmployeeAsUser', ['name' => $user->name]),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Function to check given key in the database
     *
     * @param string $key
     * @param string $value
     * @return array
     */
    public function checkFieldsUnique(string $key, string $value)
    {
        $data = $this->repo->list(
            'id',
            "{$key} = '{$value}'",
            [],
            '',
            1
        );

        return generalResponse(
            'success',
            false,
            [
                'is_available' => count($data) > 0 ? false : true,
            ],
        );
    }

    /**
     * Get specific data by id
     *
     * @param string $uid
     * @param string $select
     * @param array $relation
     *
     * @return array
     */
    public function show(
        string $uid,
        string $select = '*',
        array $relation = []
    ): array
    {
        try {
            $data = $this->repo->show($uid, $select, $relation);

            $data->makeHidden('id','position_id', 'boss_id');

            if ($data->position) $data->position->makeHidden('id');
            if ($data->employee_signs) $data->employee_signs->makeHidden('employee_id');
            if ($data->boss) $data->boss->makeHidden('id');

            if ($data->bank_detail) {
                $data['bank_detail'] = json_decode($data->bank_detail, true);
            }
            if ($data->relation_contact) {
                $data['relation_contact'] = json_decode($data->relation_contact, true);
            }
            
            return generalResponse(
                'Success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Store data
     *
     * @param array $data
     *
     * @return array
     */
    public function store(array $data): array
    {
        try {
            $data['bank_detail'] = json_encode($data['banks']);
            $data['relation_contact'] = json_encode($data['relation']);
            $data['education'] = $data['educations']['education'];
            $data['education_name'] = $data['educations']['education_name'];
            $data['education_major'] = $data['educations']['education_major'];
            $data['education_year'] = $data['educations']['graduation_year'];
            $data['level_staff'] = $data['level'];
            $data['join_date'] = date('Y-m-d', strtotime($data['join_date']));
            $data['start_review_probation_date'] = isset($data['start_review_date']) ? date('Y-m-d', strtotime($data['start_review_date'])) : null;
            $data['end_probation_date'] = isset($data['end_probation_date']) ? date('Y-m-d', strtotime($data['end_probation_date'])) : null;
            $data['company_name'] = $data['company'];
            $data['date_of_birth'] = $data['date_of_birth'] != 'null' ? date('Y-m-d', strtotime($data['date_of_birth'])) : null;
            $data['position_id'] = getIdFromUid($data['position_id'], new Position());
            if (isset($data['boss_id'])) {
                $data['boss_id'] = getIdFromUid($data['boss_id'], new \Modules\Hrd\Models\Employee());
            }

            if ($data['id_number_photo']) {
                $this->idCardPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['id_number_photo']
                );
                $data['id_number_photo'] = $this->idCardPhotoTmp;
            }

            if ($data['npwp_photo']) {
                $this->npwpPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['npwp_photo']
                );
                $data['npwp_photo'] = $this->npwpPhotoTmp;
            }

            if ($data['bpjs_photo']) {
                $this->bpjsPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['bpjs_photo']
                );
                $data['bpjs_photo'] = $this->bpjsPhotoTmp;
            }

            if ($data['kk_photo']) {
                $this->kkPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['kk_photo']
                );
                $data['kk_photo'] = $this->kkPhotoTmp;
            }

            $this->repo->store(collect($data)->except([
                'banks',
                'relation',
                'educations',
                'level',
                'start_review_date',
                'company',
                'id_card_photo'
            ])->toArray());

            return generalResponse(
                __("global.successCreateEmployee"),
                false,
                [],
            );
        } catch (\Throwable $th) {
            if ($this->idCardPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->idCardPhotoTmp));
            }
            if ($this->npwpPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->npwpPhotoTmp));
            }
            if ($this->bpjsPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->bpjsPhotoTmp));
            }
            if ($this->kkPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->kkPhotoTmp));
            }

            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Update data
     *
     * @param string $uid
     * @param array $data
     * @return array
     */
    public function update(
        array $data,
        string $uid='',
        string $where=''
    ): array
    {
        try {
            $deletedImages = $data['deleted_image'] ?? [];
            $employee = $this->repo->show($uid, 'id,id_number_photo,npwp_photo,kk_photo,bpjs_photo');

            $data['bank_detail'] = json_encode($data['banks']);
            $data['relation_contact'] = json_encode($data['relation']);
            $data['education'] = $data['educations']['education'];
            $data['education_name'] = $data['educations']['education_name'];
            $data['education_major'] = $data['educations']['education_major'];
            $data['education_year'] = $data['educations']['graduation_year'];
            $data['current_address'] = $data['current_address'] != 'null' ? $data['current_address'] : null;
            $data['level_staff'] = $data['level'];
            $data['join_date'] = date('Y-m-d', strtotime($data['join_date']));
            $data['start_review_probation_date'] = (isset($data['start_review_date']) && ($data['start_review_date'] != 'null')) ? date('Y-m-d', strtotime($data['start_review_date'])) : null;
            $data['end_probation_date'] = (isset($data['end_probation_date']) && $data['end_probation_date'] != 'null') ? date('Y-m-d', strtotime($data['end_probation_date'])) : null;
            $data['company_name'] = $data['company'];
            $data['date_of_birth'] = $data['date_of_birth'] != 'null' ? date('Y-m-d', strtotime($data['date_of_birth'])) : null;
            $data['position_id'] = getIdFromUid($data['position_id'], new Position());
            $data['province_id'] = isset($data['province_id']) ? $data['province_id'] : null;
            $data['city_id'] = isset($data['city_id']) ? $data['city_id'] : null;
            $data['district_id'] = isset($data['district_id']) ? $data['district_id'] : null;
            $data['village_id'] = isset($data['village_id']) ? $data['village_id'] : null;
            $data['dependant'] = $data['dependents'] != null ? $data['dependents'] : null;

            if (isset($data['boss_id'])) {
                $data['boss_id'] = getIdFromUid($data['boss_id'], new \Modules\Hrd\Models\Employee());
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['id_number_photo']))) {
                $data['id_number_photo'] = null;
            }
            if (isset($data['id_number_photo'])) {
                $this->idCardPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['id_number_photo']
                );
                $data['id_number_photo'] = $this->idCardPhotoTmp;
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['npwp_photo']))) {
                $data['npwp_photo'] = null;
            }
            if (isset($data['npwp_photo'])) {
                $this->npwpPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['npwp_photo']
                );
                $data['npwp_photo'] = $this->npwpPhotoTmp;
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['bpjs_photo']))) {
                $data['bpjs_photo'] = null;
            }
            if (isset($data['bpjs_photo'])) {
                $this->bpjsPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['bpjs_photo']
                );
                $data['bpjs_photo'] = $this->bpjsPhotoTmp;
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['kk_photo']))) {
                $data['kk_photo'] = null;
            }
            if (isset($data['kk_photo'])) {
                $this->kkPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['kk_photo']
                );
                $data['kk_photo'] = $this->kkPhotoTmp;
            }

            $payloadUpdate = collect($data)->except([
                'banks',
                'relation',
                'educations',
                'level',
                'start_review_date',
                'company',
                'id_card_photo',
                'is_residence_same',
                'dependents',
                'deleted_image'
            ])->toArray();

            $this->repo->update($payloadUpdate, $uid);

            \Modules\Hrd\Jobs\DeleteImageJob::dispatch($deletedImages);

            return generalResponse(
                __("global.successUpdateEmployee"),
                false,
                [
                    'payload' => $payloadUpdate,
                    'data' => $data,
                ],
            );
        } catch (\Throwable $th) {
            if ($this->idCardPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->idCardPhotoTmp));
            }
            if ($this->npwpPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->npwpPhotoTmp));
            }
            if ($this->bpjsPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->bpjsPhotoTmp));
            }
            if ($this->kkPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->kkPhotoTmp));
            }

            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Delete selected data
     *
     * @param string $uid
     *
     * @return array
     */
    public function delete(string $uid): array
    {
        try {
            $data = $this->repo->show($uid,'id,name', [
                'employee_signs:id,employee_id,sign',
//                'inventory_requests:id,request_by,uid',
            ]);

            $employeeErrorStatus = false;

            if ($data->employee_signs->count() > 0) {
                $employeeErrorRelation[] = 'employee signs';
                $employeeErrorStatus = true;
            }

//            if ($data->inventory_requests->count() > 0) {
//                $employeeErrorRelation[] = 'inventory requests';
//                $employeeErrorStatus = true;
//            }

            if ($employeeErrorStatus) {
                throw new EmployeeException(__("global.employeeRelationFound", [
                    'name' => $data->name,
                    'relation' => implode(' and ',$employeeErrorRelation)
                ]));
            }

            $this->repo->delete($uid);

            return generalResponse(
                __("global.successDeletePosition"),
                false,
                [],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Delete bulk data
     *
     * @param array $ids
     * 
     * @return array
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $images = [];
            foreach ($ids as $id) {
                $employee = $this->repo->show($id, 'id,id_number_photo,npwp_photo,kk_photo,bpjs_photo');

                if ($employee->id_number_photo) {
                    $images[] = $employee->id_number_photo;
                }
                if ($employee->nwp_photo) {
                    $images[] = $employee->npwp_photo;
                }
                if ($employee->kk_photo) {
                    $images[] = $employee->kk_photo;
                }
                if ($employee->bpjs_photo) {
                    $images[] = $employee->bpjs_photo;
                }
            }

            $this->repo->bulkDelete($ids, 'uid');

            \Modules\Hrd\Jobs\DeleteImageJob::dispatch($images);

            return generalResponse(
                __('global.successDeleteEmployee'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getProjectManagers()
    {
        $data = $this->repo->list('id, uid as value, name as title', '', [], '', '', [
            'relation' => 'position',
            'query' => "LOWER(name) like 'project manager'",
        ]);

        return generalResponse(
            'success',
            false, 
            $data->toArray(),
        );
    }
}
