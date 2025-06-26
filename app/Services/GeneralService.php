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

    public function generateInvoiceNumber(): string
    {
        $repo = new TransactionRepository();
        $transactions = $repo->list(select: 'id')->count() + 1;

        return "DF - " . generateSequenceNumber(number: $transactions, length: 5);
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
