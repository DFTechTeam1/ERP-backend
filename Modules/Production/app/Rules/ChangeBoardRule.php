<?php

namespace Modules\Production\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ChangeBoardRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value && request('board_source_id')) {
            $target = $value;
            $targetBoard = \Modules\Production\Models\ProjectBoard::select('based_board_id')->find($target);
            $targetBasedBoard = $targetBoard->based_board_id;
            $valueBoard = \Modules\Production\Models\ProjectBoard::select('based_board_id')->find(request('board_source_id'));
            $valueBasedBoard = $valueBoard->based_board_id;

            $isBacklog = getSettingByKey('board_as_backlog');
            $onProcess = getSettingByKey('board_start_calculated');
            $checkByPm = getSettingByKey('board_to_check_by_pm');
            $checkByClient = getSettingByKey('board_to_check_by_client');
            $revise = getSettingByKey('board_revise');
            $completed = getSettingByKey('board_completed');

            if (
                ($valueBasedBoard == $isBacklog && $targetBasedBoard == $checkByPm) ||
                ($valueBasedBoard == $isBacklog && $targetBasedBoard == $checkByClient) ||
                ($valueBasedBoard == $isBacklog && $targetBasedBoard == $completed)
            ) {
                $fail(__('global.cannotMoveToTargetBoard'));
            }

            if (
                ($valueBasedBoard == $onProcess && $targetBasedBoard == $checkByClient) ||
                ($valueBasedBoard == $onProcess && $targetBasedBoard == $isBacklog) ||
                ($valueBasedBoard == $onProcess && $targetBasedBoard == $completed) ||
                ($valueBasedBoard == $onProcess && $targetBasedBoard == $revise)
            ) {
                $fail(__('global.cannotMoveToTargetBoard'));
            }

            if (
                ($valueBasedBoard == $checkByPm && $targetBasedBoard == $completed) ||
                ($valueBasedBoard == $checkByPm && $targetBasedBoard == $isBacklog) ||
                ($valueBasedBoard == $checkByPm && $targetBasedBoard == $onProcess)
            ) {
                $fail(__('global.cannotMoveToTargetBoard'));
            }

            if (
                ($valueBasedBoard == $completed && $targetBasedBoard == $isBacklog) ||
                ($valueBasedBoard == $completed && $targetBasedBoard == $onProcess) ||
                ($valueBasedBoard == $completed && $targetBasedBoard == $checkByPm) ||
                ($valueBasedBoard == $completed && $targetBasedBoard == $checkByClient)
            ) {
                $fail(__('global.cannotMoveToTargetBoard'));
            }
        }
    }
}
