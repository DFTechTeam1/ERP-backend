<?php

/**
 * This is a brigde between apps and all global helper functions
 * All services, repo, controller should call this service when they need to communicate with global function
 */

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Repository\ProjectDealRepository;

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
                $number = $currentData[0]['identifier_number'] + 1;
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
}
