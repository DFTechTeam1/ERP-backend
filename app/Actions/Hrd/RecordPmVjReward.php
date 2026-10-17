<?php

namespace App\Actions\Hrd;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\EmployeeReward;
use Modules\Production\Models\Project;
use Modules\Production\Repository\ProjectRepository;

/**
 * Record the fixed PM and VJ rewards for a completed project.
 *
 * Unlike the production reward, these are NOT point-based. They are fixed per project class
 * (docs/Simulasi_Baseline_Pot_Produksi_Fixed_DFactory.xlsx):
 *
 *   - PM: a matching project_class_pm_tier (by PM headcount) sets explicit per-role amounts -
 *     lead_reward for the Lead PM and support_reward for each Support PM. The Lead is the PIC
 *     flagged is_lead (falling back to the earliest-assigned PIC). For an untiered class, or a
 *     headcount with no matching tier, the flat project_classes.pm_reward pot is split by headcount
 *     instead (1 PM: 100%; 2 PM: 70/30; 3 PM: 50/25/25; more: equally), the last row absorbing the
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
                'projectClass.tiers',
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
        $pics = $project->personInCharges;

        if ($pics->isEmpty()) {
            return;
        }

        // Lead first, then supports (by assignment order). Lead is the flagged PIC, else the
        // earliest-assigned one.
        $lead = $pics->firstWhere('is_lead', true) ?? $pics->sortBy('id')->first();
        $supports = $pics->reject(fn ($pic) => $pic->id === $lead->id)->sortBy('id')->values();

        $tier = $project->projectClass->tiers->isNotEmpty()
            ? $project->projectClass->tiers->firstWhere('pm_count', $pics->count())
            : null;

        $className = $project->projectClass->name;

        // Tiered class: the tier sets explicit per-role amounts - lead_reward for the Lead PM and
        // support_reward for every Support PM (no headcount percentage split). base_reward records
        // the tier's total PM pot for reference.
        if ($tier) {
            $pot = (float) $tier->pm_reward;

            $this->storeReward(
                projectId: $project->id,
                employeeId: $lead->pic_id,
                role: 'pm',
                baseReward: $pot,
                totalReward: (float) $tier->lead_reward,
                className: $className,
            );

            foreach ($supports as $support) {
                $this->storeReward(
                    projectId: $project->id,
                    employeeId: $support->pic_id,
                    role: 'pm',
                    baseReward: $pot,
                    totalReward: (float) $tier->support_reward,
                    className: $className,
                );
            }

            return;
        }

        // Untiered class (or no tier for this headcount): split the flat pm_reward pot by headcount.
        $pmPot = (float) ($project->projectClass->pm_reward ?? 0);
        if ($pmPot <= 0) {
            return;
        }

        $ordered = collect([$lead])->concat($supports)->values();
        $amounts = $this->distributeFixedPot($pmPot, $this->pmSplitPercentages($ordered->count()));

        foreach ($ordered as $index => $pic) {
            $this->storeReward(
                projectId: $project->id,
                employeeId: $pic->pic_id,
                role: 'pm',
                baseReward: $pmPot,
                totalReward: $amounts[$index],
                className: $className,
            );
        }
    }

    protected function recordVjReward(mixed $project): void
    {
        // VJ reward is a flat amount PER VJ (never tiered); every VJ earns the full class amount.
        $vjReward = (float) ($project->projectClass->vj_reward ?? 0);

        foreach ($project->vjs as $vj) {
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
