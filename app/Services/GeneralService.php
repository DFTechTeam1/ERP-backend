<?php

/**
 * This is a brigde between apps and all global helper functions
 * All services, repo, controller should call this service when they need to communicate with global function
 */

namespace App\Services;

use App\Enums\Production\ProjectDealStatus;
use App\Enums\System\BaseRole;
use App\Enums\Transaction\InvoiceStatus;
use App\Enums\Transaction\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Repository\ProjectDealRepository;
use Vinkla\Hashids\Facades\Hashids;

class GeneralService
{
    public function getIdFromUid(string $uid, $model)
    {
        return getIdFromUid($uid, $model);
    }

    public function getSettingByKey(string $param)
    {
        return getSettingByKey($param);
    }

    public function formatNotifications(array $param)
    {
        return formatNotifications($param);
    }

    public function getClientIp()
    {
        return getClientIp();
    }

    public function parseUserAgent($param)
    {
        return parseUserAgent($param);
    }

    public function getUserAgentInfo()
    {
        return getUserAgentInfo();
    }

    public function generateRandomPassword(int $length)
    {
        return generateRandomPassword($length);
    }

    public function getCache(string $cacheId)
    {
        return getCache($cacheId);
    }

    public function clearCache(string $cacheId)
    {
        clearCache($cacheId);
    }

    public function storeCache(string $key, mixed $value, int $ttl = 60 * 60 * 6, bool $isForever = false)
    {
        storeCache($key, $value, $ttl, $isForever);
    }

    public function uploadImageandCompress(
        string $path,
        int $compressValue,
        $image,
        string $extTarget = 'webp',
    ) {
        return uploadImageandCompress($path, $compressValue, $image, $extTarget);
    }

    public function reportPerformanceDefaultDate(): array
    {
        $now = Carbon::now();

        $startDate = $now->copy()->subMonthNoOverflow()->day(24);
        $endDate = $now->copy()->day(23);

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    public function generateRandomColor(string $email)
    {
        return generateRandomColor($email);
    }

    public function linkShortener(int $length = 8): string
    {
        return linkShortener($length);
    }

    public function generateSequenceNumber($number, $length = 4)
    {
        return str_pad($number, $length, 0, STR_PAD_LEFT);
    }

    /**
     * Generate identifier number for each project deal
     * 
     * This will increase every time
     * This identifier number will be used as 'DESIGN JOB' in the quotation and as SUFFIX NUMBER on invoice
     * 
     * The output will be like 0950 or 01001 and so on
     *
     * @return string
     */
    public function generateDealIdentifierNumber(): string
    {
        $cutoff = 950;

        $number = $this->getCache(cacheId: \App\Enums\Cache\CacheKey::ProjectDealIdentifierNumber->value);

        if (!$number) {
            $repo = new ProjectDealRepository();
            $currentData = $repo->list(
                select: 'id,identifier_number',
                limit: 1,
                orderBy: 'created_at DESC',
                withDeleted: true
            )->toArray();
    
            if (count($currentData) == 0) {
                $number = $cutoff + 1;
            } else {
                if (!$currentData[0]['identifier_number']) {
                    $number = $cutoff + 1;
                } else {
                    $number = $currentData[0]['identifier_number'] + 1;
                }
            }

            // convert to sequence number
            $lengthOfSentence = strlen($number) < 4 ? 4 : strlen($number) + 1;
            $number = $this->generateSequenceNumber(number: $number, length: $lengthOfSentence);

            $this->storeCache(key: \App\Enums\Cache\CacheKey::ProjectDealIdentifierNumber->value, value: $number, isForever: true);
        }

        return $number;
    }

    public function generateInvoiceNumber(string $identifierNumber, ?string $date = null): string
    {
        $datetime = $date ? now()->parse($date) : now();
        $romanMonth = $this->monthToRoman(month: (int) $datetime->format('m'));
        $year = $datetime->format('Y');

        return "{$romanMonth}/{$year} - {$identifierNumber}";
    }

    /**
     * Generate romawi month
     * 
     * @param int $month
     * 
     * @return string
     */
    public function monthToRoman(int $month): string
    {
        // Validate input
        if (!is_numeric($month) || $month < 1 || $month > 12) {
            return "Invalid month";
        }

        $romanNumerals = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII'
        ];

        return $romanNumerals[$month];
    }

    /**
     * Get list of payment that not paid yet and have final status
     *
     * @return array
     */
    public function getUpcomingPaymentDue(): Collection
    {
        $repo = new ProjectDealRepository();

        // only get final project deal and not fully paid
        $where = "status = " . \App\Enums\Production\ProjectDealStatus::Final->value . " AND is_fully_paid = 0 AND DATEDIFF(project_date, CURRENT_DATE) BETWEEN 1 AND 5";

        $data = $repo->list(
            select: 'id,customer_id,name,DATEDIFF(project_date, CURRENT_DATE) as interval_due,project_date,city_id,country_id,is_fully_paid',
            where: $where,
            relation: [
                'marketings:id,project_deal_id,employee_id',
                'marketings.employee:id,user_id,email',
                'customer:id,name',
                'city:id,name',
                'country:id,name',
                'transactions',
                'finalQuotation'
            ],
            whereHas: [
                [
                    'relation' => 'finalQuotation',
                    'query' => 'id > 0'
                ]
            ]
        );

        return $data;
    }

    public function setProjectIdentifier()
    {
        // get current identifier number from cache
        $currentIdentifier = (new \App\Services\GeneralService)->generateDealIdentifierNumber();

        // increase value of the identifier number
        (new \App\Services\GeneralService)->clearCache(cacheId: \App\Enums\Cache\CacheKey::ProjectDealIdentifierNumber->value);
        $nextIdentifier = (int) $currentIdentifier + 1;
        // convert to sequence number
        $lengthOfSentence = strlen($nextIdentifier) < 4 ? 4 : strlen($nextIdentifier) + 1;
        $nextIdentifier = (new \App\Services\GeneralService)->generateSequenceNumber(number: $nextIdentifier, length: $lengthOfSentence);
        (new \App\Services\GeneralService)->storeCache(key: \App\Enums\Cache\CacheKey::ProjectDealIdentifierNumber->value, value: $nextIdentifier, isForever: true);

        return $currentIdentifier;
    }

    /**
     * Get all invoice that have due from now to the next 5 days
     * 
     * Finance and marketing will be notified about this
     * 
     * @return Collection
     */
    public function getInvoiceDueData(): Collection
    {
        $repo = new InvoiceRepository();

        $data = $repo->list(
            select: 'id,project_deal_id,customer_id,number,amount,payment_due,status',
            where: "DATEDIFF(payment_due, CURRENT_DATE) BETWEEN 1 AND 5 AND status = " . InvoiceStatus::Unpaid->value,
            relation: [
                'projectDeal:id,name',
                'customer:id,name',
                'projectDeal.marketings:id,employee_id,project_deal_id'
            ]
        );

        return $data;
    }

    public function getProjectDealSummary(string|int $year): array
    {
        try {
            $repo = new ProjectDealRepository();
            $data = $repo->list(
                select: "id,name,collaboration,project_date,city_id,led_area,venue",
                relation: [
                    'city:id,name',
                    'marketings:id,project_deal_id,employee_id',
                    'marketings.employee:id,nickname',
                    'finalQuotation',
                    'transactions'
                ],
                where: "status = " . ProjectDealStatus::Final->value . " AND YEAR(project_date) = {$year}"
            );

            $data = $data->map(function ($project) {
                $project['marketing_name'] = $project->marketings->pluck('employee.nickname')->implode(',');

                // get down payment
                $downPayment = 0;
                $downPaymentDate = '';
                if ($project->transactions->count() > 0) {
                    $downPaymentRaw = $project->transactions->where('transaction_type', TransactionType::DownPayment->value)->values();
                    $downPayment = $downPaymentRaw->count() > 0 ? (float) $downPaymentRaw[0]->payment_amount : 0;
                    $downPaymentDate = $downPaymentRaw->count() > 0 ? date('d F Y', strtotime($downPaymentRaw[0]->transaction_date)) : '';
                }
                $project['down_payment'] = $downPayment;
                $project['down_payment_date'] = $downPaymentDate;

                // get repayment
                $repayment = 0;
                $repaymentDate = '';
                if ($project->transactions->count() > 0) {
                    $repaymentRaw = $project->transactions->where('transaction_type', TransactionType::Repayment->value)->values();
                    $repayment = $repaymentRaw->count() > 0 ? (float) $repaymentRaw[0]->payment_amount : 0;
                    $repaymentDate = $repaymentRaw->count() > 0 ? date('d F Y', strtotime($repaymentRaw[0]->transaction_date)) : '';
                }
                $project['repayment'] = $repayment;
                $project['repayment_date'] = $repaymentDate;

                //TODO: Build the feature
                $project['refund'] = 0;
                $project['refund_date'] = '';

                return $project;
            })->values();

            return generalResponse(
                message: "Success",
                data: [
                    'projects' => $data
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getDataForRequestInvoiceChangeNotification(int $invoiceRequestId): array
    {
        $data = \Modules\Finance\Models\InvoiceRequestUpdate::with([
                'invoice:id,uid,parent_number,amount,payment_date,customer_id,project_deal_id,number',
                'invoice.customer:id,name',
                'invoice.projectDeal:id,name'
            ])
            ->find($invoiceRequestId);

        $changes = [];
        if (
            ($data->invoice->amount != $data->amount) &&
            ($data->amount)
        ) {
            $changes['amount'] = [
                'old' => "Rp" . number_format(num: $data->invoice->amount, decimal_separator: ','),
                'new' => "Rp" . number_format(num: $data->amount, decimal_separator: ',')
            ];
        }
        if (
            (date('Y-m-d', strtotime($data->invoice->payment_date)) != date('Y-m-d', strtotime($data->payment_date))) &&
            ($data->payment_date)
        ) {
            $changes['payment_date'] = [
                'old' => date('Y-m-d', strtotime($data->invoice->payment_date)),
                'new' => $data->payment_date
            ];
        }

        $actor = \App\Models\User::with(['employee:id,user_id,name'])
            ->find($data->request_by);

        // this cannot be null
        $director = \Modules\Hrd\Models\Employee::with(['user:id,employee_id,uid'])
            ->where('email', 'wesleywiyadi@gmail.com') 
            ->first();

        $output = [
            'actor' => $actor,
            'invoice' => $data,
            'director' => $director,
            'changes' => $changes,
        ];

        return $output;
    }

    public function generateAuthorizedUserToken(\App\Models\User $user): array
    {
        $role = $user->getRoleNames()[0];
        $roles = $user->roles;

        $roleId = null;
        if (count($roles) > 0) {
            $roleId = $roles[0]->id;
        }
        $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];

        $expireTime = now()->addHours(24);
        if (isset($validated['remember_me'])) {
            $expireTime = now()->addDays(30);
        }

        $token = $user->createToken($role, $permissions, $expireTime);

        return [
            'token' => $token,
            'user' => $user,
            'roles' => $roles,
            'role' => $role,
            'role_id' => $roleId,
            'permissions' => $permissions
        ];
    }

    public function authorizeReportingAccess(string $email): string
    {
        $response = \Illuminate\Support\Facades\Http::post(
            url: config('app.python_endpoint') . '/auth/access-token',
            data: [
                'email' => $email
            ]
        );

        if ($response->status() != 200) {
            throw new \App\Exceptions\UserNotFound(message: "Failed to generate token");
        }

        $token = $response->json()['data']['access_token'];

        return $token;
    }

    /**
     * Encrypted payload:           With these format:
     *      - token exp
     *      - user
     *      - role
     *      - roleId
     *      - appName
     *      - notifications: []
     *      - encryptedUserId
     *      - notificationSection                With these format
     *          - general
     *          - finance
     *          - hrd
     *          - production
     *
     * @return array
     */
    public function getEncryptedPayloadData(array $tokenizer): array
    {
        $allRoles = \App\Enums\System\BaseRole::cases();
        $allRoles = collect($allRoles)->map(function ($roleData) {
            return $roleData->value;
        })->toArray();

        $user = $tokenizer['user'];
        $exp = date('Y-m-d H:i:s', strtotime($tokenizer['token']->accessToken->expires_at));
        $userIdEncode = Hashids::encode($user->id);

        return [
            'exp' => $exp,
            'user' => $tokenizer['user'],
            'role' => $tokenizer['role'],
            'role_id' => $tokenizer['role_id'],
            'app_name' => $this->getSettingByKey('app_name'),
            'notifications' => [],
            'encrypted_user_id' => $userIdEncode,
            'notification_section' => [
                'general' => $user->hasRole($allRoles),
                'finance' => $user->hasRole([BaseRole::Finance->value, BaseRole::Root->value, BaseRole::Director->value]),
                'production' => $user->hasRole([BaseRole::Root->value, BaseRole::Director->value, BaseRole::ProjectManager->value, BaseRole::ProjectManagerAdmin->value, BaseRole::ProjectManagerEntertainment->value, BaseRole::Production->value]),
                'hrd' => $user->hasRole([BaseRole::Root->value, BaseRole::Director->value, BaseRole::Hrd->value]),
            ]
        ];
    }

    /**
     * Here we define all variable that will be saved in the frontend
     *
     * @param \App\Models\User $user
     * @return array
     */
    public function generateAuthorizationToken(\App\Models\User $user): array
    {
        $tokenizer = $this->generateAuthorizedUserToken(user: $user);

        $roles = $tokenizer['roles'];

        $token = $tokenizer['token'];

        $encryptedPayload = $this->getEncryptedPayloadData(tokenizer: $tokenizer);
        
        // generate reporting token
        $reportingToken = $this->authorizeReportingAccess(email: $user->email);
        
        $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];
        
        $menus = (new \App\Services\MenuService)->getNewFormattedMenu($user->getAllPermissions()->toArray(), $roles->toArray());
        
        $encryptionService = new \App\services\EncryptionService();
        $encryptedPayload = $encryptionService->encrypt(string: json_encode($encryptedPayload), key: config('app.salt_key_encryption'));
        $permissionsEncrypted = $encryptionService->encrypt(string: json_encode([
            'permissions' => $permissions
        ]), key: config('app.salt_key_encryption'));
        $menusEncrypted = $encryptionService->encrypt(string: json_encode([
            'menus' => $menus
        ]), key: config('app.salt_key_encryption'));

        return [
            'encryptedPayload' => $encryptedPayload,
            'reportingToken' => $reportingToken,
            'pEnc' => $permissionsEncrypted,
            'mEnc' => $menusEncrypted,
            'mainToken' => $token->plainTextToken,
            'menus' => $menus
        ];
    }

    /**
     * Here we'll export project deals summary based on user selection
     * In the result, we will display:
     * - project name
     * - project date
     * - marketing name
     * - venue
     * - total led area
     * - fix price
     * - history of payments
     * - due amount
     *
     * @param array $payload            With these following structure:
     * - string $date_range
     * - array $marketings
     * - array $status
     * - array $price
     */
    public function getFinanceExportData(array $payload)
    {
        $where = "id > 0";
        $whereHas = [];

        // filter based on project date
        if (!empty($payload['date_range'])) {
            $explodeDate = explode(' - ', $payload['date_range']);

            if (isset($explodeDate[1])) {
                $where .= " AND project_date BETWEEN '" . $explodeDate[0] . "' AND '" . $explodeDate[1] . "'";
            } else {
                $where .= " AND project_date >= '" . $explodeDate[0] . "'";
            }
        }

        // filter based on marketings
        if (!empty($payload['marketings'])) {
            $marketings = collect($payload['marketings'])->map(function ($marketing) {
                return $this->getIdFromUid(uid: $marketing, model: new \Modules\Hrd\Models\Employee());
            })->implode(',');

            $whereHas[] = [
                'relation' => 'marketings',
                'query' => "employee_id IN ({$marketings})"
            ];
        }

        // filter based on status
        if (!empty($payload['status'])) {
            $where .= " AND status IN (" . implode(',', $payload['status']) . ")";
        }

        // filter based on price
        if (!empty($payload['price'])) {
            $price = $payload['price'];
            $whereHas[] = [
                'relation' => 'finalQuotation',
                'query' => "fix_price BETWEEN " . $price[0] . " AND " . $price[1]
            ];
        }

        $data = (new \Modules\Production\Repository\ProjectDealRepository)->list(
            select: 'id,name,project_date,country_id,state_id,city_id',
            where: $where,
            whereHas: $whereHas,
            relation: [
                'marketings:id,employee_id',
                'marketings.employee:id,name',
                'finalQuotation',
                'transactions'
            ]
        );

        $data = $data->map(function ($project) {
            $project['marketingName'] = $project->marketings->pluck('employee.name')->implode(',');
            $project['projectDateFormat'] = date('d F Y', strtotime($project->project_date));

            return $project;
        })->values();

        return $data;
    }
}
