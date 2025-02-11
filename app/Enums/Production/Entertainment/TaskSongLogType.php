<?php

namespace App\Enums\Production\Entertainment;

enum TaskSongLogType: string {
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
            case static::AssignJob->value:
                // $text = __('log.userHasBeenAssigned', ['givenBy' => $payload['givenBy'] ?? '-', 'target' => $payload['target'] ?? '']);
                $text = 'log.userHasBeenAssigned';
                break;

            case static::Approved->value:
                // $text = __('log.userApprovedTask', ['user' => $payload['user']]);
                $text = 'log.userApprovedTask';
                break;

            case static::Completed->value:
                // $text = __('log.userCompleteJob', ['user' => $payload['user']]);
                $text = 'log.userCompleteJob';
                break;

            case static::CheckByPM->value:
                // $text = __('log.PMCheckJob', ['user' => $payload['user']]);
                $text = 'log.PMCheckJob';
                break;

            case static::ApprovedByPM->value:
                // $text = __('log.songApprovedByPM', ['pm' => $payload['pm'], 'user' => $payload['user']]);
                $text = 'log.songApprovedByPM';
                break;

            case static::RevisedByPM->value:
                // $text = __('log.songRevisedByPM', ['pm' => $payload['pm'], 'user' => $payload['user']]);
                $text = 'log.songRevisedByPM';
                break;

            case static::CheckByPMProject->value:
                // $text = __('log.checkByPMProject');
                $text = 'log.checkByPMProject';
                break;

            case static::JobCompleted->value:
                // $text = __('log.songJobComplete');
                $text = 'log.songJobComplete';
                break;

            case static::RevisedByPMProject->value:
                // $text = __('log.songRevisedByPMProject');
                $text = 'log.songRevisedByPMProject';
                break;

            case static::DelegateByPM->value:
                // $text = __('log.songDelegateByPM', ['user' => $payload['user']]);
                $text = 'log.songDelegateByPM';
                break;

            case static::ChangePICByPM->value:
                // $text = __('log.songChangePICByPM', ['pm' => $payload['pm'], 'user' => $payload['user'], 'target' => $payload['target']]);
                $text = 'log.songChangePICByPM';
                break;

            case static::ApprovedRequestEdit->value:
                $text = 'log.songApproveRequestEdit';
                break;

            case static::RejectRequestEdit->value:
                $text = 'log.songRejectRequestEdit';
                break;

            case static::RemoveWorkerFromTask->value:
                $text = 'log.songRemoveWorker';
                break;

            case static::RequestToEditSong->value:
                $text = 'log.requestToEditSong';
                break;

            case static::EditSong->value:
                $text = 'log.editSong';
                break;

            case static::StartWorking->value:
                $text = 'log.startWorkSong';
                break;

            case static::ReportAsDone->value:
                $text = 'log.songReportAsDone';
                break;

            case static::ApprovedByEntertainmentPM->value:
                $text = 'log.songApprovedByEntertainmentPM';
                break;

            case static::ApprovedByEventPM->value:
                $text = 'log.songApprovedByEventPM';
                break;
            
            default:
                $text = '-';
                break;
        }

        return $text;
    }
}