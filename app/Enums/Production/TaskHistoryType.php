<?php

namespace App\Enums\Production;

enum TaskHistoryType: string
{
    case CrossTeamCollaboration = 'cross_team_collaboration';
    case SingleAssignee = 'single_assignee';
    case TemporaryTransfer = 'temporary_transfer';
    case SplitAssignment = 'split_assignment';
}
