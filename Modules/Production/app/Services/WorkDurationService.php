<?php

namespace Modules\Production\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class WorkDurationService
{
    /**
     * Set hold duration
     * 
     * @param int $holdDuratoin
     * @param Collection $holdStates
     * @return void
     */
    public function setHoldstateDuration(int &$holdDuratoin, Collection $holdStates): void
    {
        foreach ($holdStates as $holdState) {
            $holdDuratoin += Carbon::parse($holdState->started_at)->diffInSeconds(Carbon::parse($holdState->ended_at) ?? now());
        }
    }

    /**
     * Set revise duration
     * 
     * @param int $revisedDuration
     * @param Collection $reviseStates
     * @return void
     */
    public function setReviseDuration(int &$revisedDuration, Collection $reviseStates): void
    {
        // group by employees, and only calculate from 1 employee
        $reviseStatesByEmployee = $reviseStates->groupBy('employee_id')->map(function ($group) {
            return $group->first();
        });
        foreach ($reviseStatesByEmployee as $reviseState) {
            $revisedDuration += Carbon::parse($reviseState->start_at)->diffInSeconds(Carbon::parse($reviseState->finish_at) ?? now());
        }
    }
}