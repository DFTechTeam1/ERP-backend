<?php

namespace App\Enums\Production\Entertainment;

enum TaskSongLogType: string
{
    case AssignJob = 'assignJob';
    case Approved = 'approved';
    case Completed = 'completed';
    case CheckByPM = 'checkByPM';
    case ApprovedByPM = 'approvedByPM';
    case RevisedByPM = 'reviseByPM';
    case CheckByPMProject = 'checkByPMProject';
    case JobCompleted = 'jobCompleted';
    case RevisedByPMProject = 'reviseByPMProject';
    case DelegateByPM = 'delegateByPM';
    case ChangePICByPM = 'changePICByPM';
    case ApprovedRequestEdit = 'approveRequestEdit';
    case RejectRequestEdit = 'rejectRequestEdit';
    case RemoveWorkerFromTask = 'removeWorkerFromTask';
    case RequestToEditSong = 'requestToEditSong';
    case EditSong = 'editSong';
    case StartWorking = 'startWorking';
    case ReportAsDone = 'reportAsDone';
    case ApprovedByEntertainmentPM = 'approveByEntertaimentPM';
    case ApprovedByEventPM = 'approveByEventPM';

    public static function generateText(string $type, array $payload = [])
    {
        switch ($type) {
            case self::AssignJob->value:
                // $text = __('log.userHasBeenAssigned', ['givenBy' => $payload['givenBy'] ?? '-', 'target' => $payload['target'] ?? '']);
                $text = 'log.userHasBeenAssigned';
                break;

            case self::Approved->value:
                // $text = __('log.userApprovedTask', ['user' => $payload['user']]);
                $text = 'log.userApprovedTask';
                break;

            case self::Completed->value:
                // $text = __('log.userCompleteJob', ['user' => $payload['user']]);
                $text = 'log.userCompleteJob';
                break;

            case self::CheckByPM->value:
                // $text = __('log.PMCheckJob', ['user' => $payload['user']]);
                $text = 'log.PMCheckJob';
                break;

            case self::ApprovedByPM->value:
                // $text = __('log.songApprovedByPM', ['pm' => $payload['pm'], 'user' => $payload['user']]);
                $text = 'log.songApprovedByPM';
                break;

            case self::RevisedByPM->value:
                // $text = __('log.songRevisedByPM', ['pm' => $payload['pm'], 'user' => $payload['user']]);
                $text = 'log.songRevisedByPM';
                break;

            case self::CheckByPMProject->value:
                // $text = __('log.checkByPMProject');
                $text = 'log.checkByPMProject';
                break;

            case self::JobCompleted->value:
                // $text = __('log.songJobComplete');
                $text = 'log.songJobComplete';
                break;

            case self::RevisedByPMProject->value:
                // $text = __('log.songRevisedByPMProject');
                $text = 'log.songRevisedByPMProject';
                break;

            case self::DelegateByPM->value:
                // $text = __('log.songDelegateByPM', ['user' => $payload['user']]);
                $text = 'log.songDelegateByPM';
                break;

            case self::ChangePICByPM->value:
                // $text = __('log.songChangePICByPM', ['pm' => $payload['pm'], 'user' => $payload['user'], 'target' => $payload['target']]);
                $text = 'log.songChangePICByPM';
                break;

            case self::ApprovedRequestEdit->value:
                $text = 'log.songApproveRequestEdit';
                break;

            case self::RejectRequestEdit->value:
                $text = 'log.songRejectRequestEdit';
                break;

            case self::RemoveWorkerFromTask->value:
                $text = 'log.songRemoveWorker';
                break;

            case self::RequestToEditSong->value:
                $text = 'log.requestToEditSong';
                break;

            case self::EditSong->value:
                $text = 'log.editSong';
                break;

            case self::StartWorking->value:
                $text = 'log.startWorkSong';
                break;

            case self::ReportAsDone->value:
                $text = 'log.songReportAsDone';
                break;

            case self::ApprovedByEntertainmentPM->value:
                $text = 'log.songApprovedByEntertainmentPM';
                break;

            case self::ApprovedByEventPM->value:
                $text = 'log.songApprovedByEventPM';
                break;

            default:
                $text = '-';
                break;
        }

        return $text;
    }
}
