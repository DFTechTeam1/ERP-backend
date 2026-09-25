<?php

namespace App\Actions\Hrd;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\EmployeeReward;
use Modules\Production\Repository\ProjectRepository;

/**
 * Record the fixed PM and VJ rewards for a completed project.
 *
 * Unlike the production reward, these are NOT point-based. They are fixed per project class
 * (docs/Simulasi_Baseline_Pot_Produksi_Fixed_DFactory.xlsx):
 *
 *   - PM: project_classes.pm_reward is the total PM pot for the event, split by PM headcount -
 *     1 PM: Lead 100%; 2 PM: Lead 70% / Support 30%; 3 PM: Lead 50% / Support 25% / Support 25%.
 *     The Lead is the PIC flagged is_lead (falling back to the earliest-assigned PIC). More than
 *     3 PMs is not covered by the tariff, so the pot is split equally. The last row absorbs the
 *     rounding remainder so the payout equals the pot exactly.
 *   - VJ: project_classes.vj_reward is a fixed amount PER VJ; every VJ on the project earns it.
 *
 * Rewards are stored in employee_rewards with role 'pm' / 'vj' (production rewards use
 * 'production'); the point columns are 0 and there is no employee_point_project.
 */
class RecordPmVjReward
{
    use AsAction;

    public function handle(string|int $projectId): void
    {
        $project = app(ProjectRepository::class)->show(
            uid: '',
            where: "id = {$projectId}",
            select: 'id,name,project_class_id',
            relation: [
                'projectClass:id,name,pm_reward,vj_reward',
                'personInCharges:id,project_id,pic_id,is_lead',
                'vjs:id,project_id,employee_id',
            ]
        );

        if (! $project || ! $project->projectClass) {
            return;
        }

        DB::transaction(function () use ($project) {
            $this->recordPmReward($project);
            $this->recordVjReward($project);
        });
    }

    protected function recordPmReward(mixed $project): void
    {
        $pmPot = (float) ($project->projectClass->pm_reward ?? 0);
        $pics = $project->personInCharges;

        if ($pics->isEmpty() || $pmPot <= 0) {
            return;
        }

        // Lead first, then supports (by assignment order). Lead is the flagged PIC, else the
        // earliest-assigned one.
        $lead = $pics->firstWhere('is_lead', true) ?? $pics->sortBy('id')->first();
        $supports = $pics->reject(fn ($pic) => $pic->id === $lead->id)->sortBy('id')->values();
        $ordered = collect([$lead])->concat($supports)->values();

        $amounts = $this->distributeFixedPot($pmPot, $this->pmSplitPercentages($ordered->count()));

        foreach ($ordered as $index => $pic) {
            $this->storeReward(
                projectId: $project->id,
                employeeId: $pic->pic_id,
                role: 'pm',
                baseReward: $pmPot,
                totalReward: $amounts[$index],
                className: $project->projectClass->name,
            );
        }
    }

    protected function recordVjReward(mixed $project): void
    {
        $vjReward = (float) ($project->projectClass->vj_reward ?? 0);

        foreach ($project->vjs as $vj) {
            // VJ reward is a fixed amount PER VJ, so every VJ earns the full class amount.
            $this->storeReward(
                projectId: $project->id,
                employeeId: $vj->employee_id,
                role: 'vj',
                baseReward: $vjReward,
                totalReward: $vjReward,
                className: $project->projectClass->name,
            );
        }
    }

    protected function storeReward(int $projectId, int $employeeId, string $role, float $baseReward, float $totalReward, ?string $className): void
    {
        EmployeeReward::create([
            'employee_id' => $employeeId,
            'project_id' => $projectId,
            'employee_point_project_id' => null,
            'base_reward' => $baseReward,
            'total_point' => 0,
            'point' => 0,
            'additional_point' => 0,
            'total_reward' => $totalReward,
            'project_class_name' => $className,
            'role' => $role,
        ]);
    }

    /**
     * Percentages of the PM pot, Lead first then Supports, by PM headcount. The tariff defines
     * 1-3 PMs; beyond that the pot is split equally.
     *
     * @return array<int, float>
     */
    protected function pmSplitPercentages(int $count): array
    {
        return match ($count) {
            1 => [1.0],
            2 => [0.7, 0.3],
            3 => [0.5, 0.25, 0.25],
            default => array_fill(0, $count, 1 / $count),
        };
    }

    /**
     * Split a fixed pot across the given percentages, with the last share absorbing the rounding
     * remainder so the total paid equals the pot exactly.
     *
     * @param  array<int, float>  $percentages
     * @return array<int, float>
     */
    protected function distributeFixedPot(float $pot, array $percentages): array
    {
        $amounts = [];
        $roundedSum = 0.0;

        foreach ($percentages as $percentage) {
            $amount = round($pot * $percentage);
            $amounts[] = $amount;
            $roundedSum += $amount;
        }

        $lastIndex = count($amounts) - 1;
        $amounts[$lastIndex] += $pot - $roundedSum;

        return $amounts;
    }
}
