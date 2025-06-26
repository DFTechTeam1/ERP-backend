<?php

/**
 * This is a brigde between apps and all global helper functions
 * All services, repo, controller should call this service when they need to communicate with global function
 */

namespace App\Services;

use Carbon\Carbon;
use Modules\Finance\Repository\TransactionRepository;

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

    public function generateInvoiceNumber(): string
    {
        $cutoff = 950;

        $romanMonth = $this->monthToRoman(month: (int) now()->format('m'));
        $year = now()->format('Y');

        $repo = new TransactionRepository();
        $latestData = $repo->list(select: 'id,trx_id', limit: 1, orderBy: 'created_at DESC')->toArray();
        logging("LATEST TRX", $latestData);
        if (count($latestData) == 0) {
            $number = $cutoff + 1;
        } else {
            $latestNumber = explode(' - ', $latestData[0]['trx_id']);
            $number = (int) $latestNumber[1] + 1;
        }

        // convert to sequence number
        $lengthOfSentence = strlen($number) < 4 ? 4 : strlen($number) + 1;
        $number = $this->generateSequenceNumber(number: $number, length: $lengthOfSentence);

        return "{$romanMonth}/{$year} - {$number}";
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
}
