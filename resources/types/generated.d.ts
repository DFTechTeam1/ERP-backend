declare namespace App.Enums {
export type CodeDivider = 701;
}
declare namespace App.Enums.Cache {
export type CacheKey = 'employeesCache' | 'inventoriesCache' | 'projectToBeComplete' | 'hrDashboardEmployeeList' | 'hrDashboardEmploymentStatus' | 'hrDashboardLoS' | 'hrDashboardActiveStaff' | 'hrDashboardGenderDiversity' | 'hrDashboardJobLevel' | 'hrDashboardAgeAverage' | 'marketingList' | 'customerList' | 'priceGuideSetting' | 'mainLedFormula' | 'prefuncLedFormula' | 'highSeasonFormula' | 'equipmentFormula' | 'maxDiscountFormula' | 'maxMarkupFormula' | 'priceChangeReasons' | 'projectCount' | 'projectDealIdentifierNumber';
}
declare namespace App.Enums.Company {
export type ExportImportAreaType = 'old_area' | 'new_area';
}
declare namespace App.Enums.Development.Project {
export type ProjectStatus = 1 | 2 | 3 | 4;
export type ReferenceType = 'media' | 'link' | 'document';
}
declare namespace App.Enums.Development.Project.Task {
export type TaskStatus = 1 | 2 | 3 | 4 | 5 | 6 | 7;
}
declare namespace App.Enums.Employee {
export type BloodType = 'A' | 'B' | 'AB' | 'O';
export type BpjsKesehatanConfiguration = 1 | 2 | 3;
export type Education = 'smp' | 'sma' | 'smk' | 'diploma' | 's1' | 's2' | 's3';
export type EmployeeTaxStatus = 0 | 1 | 2 | 3;
export type Gender = 'female' | 'male';
export type JhtConfiguration = 0 | 1 | 2 | 3;
export type JpConfiguration = 0 | 1 | 2 | 3;
export type LevelStaff = 'manager' | 'lead' | 'staff' | 'junior_staff';
export type MartialStatus = 'single' | 'married';
export type OutOfSyncStatus = 'synced' | 'out_of_sync';
export type OvertimeStatus = 1 | 2;
export type ProbationStatus = '1' | '2' | '3' | '4';
export type PtkpStatus = '1' | '2' | '3' | '4' | '5' | '6' | '7' | '8';
export type RelationFamily = 'father' | 'mother' | 'sibling' | 'child' | 'other';
export type Religion = 'islam' | 'kristen' | 'katholik' | 'hindu' | 'budha' | 'konghucu';
export type SalaryConfiguration = 1 | 2;
export type SalaryType = 1 | 2;
export type Status = 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8;
export type TaxConfiguration = 1 | 2 | 3;
}
declare namespace App.Enums.ErrorCode {
export type Code = 201 | 200 | 400 | 401 | 403 | 404 | 405 | 422 | 500;
}
declare namespace App.Enums.ExcelTemplate {
export type Inventory = 'TEMPLATE INVENTORY LIST';
}
declare namespace App.Enums.Finance {
export type InvoiceRequestUpdateStatus = 1 | 2 | 3;
export type RefundStatus = '1' | '2';
}
declare namespace App.Enums.Interactive {
export type InteractiveProjectStatus = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8;
export type InteractiveRequestStatus = '1' | '2' | '3';
export type InteractiveTaskStatus = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
}
declare namespace App.Enums.Inventory {
export type InventoryStatus = 1 | 2 | 3 | 4 | 5 | 6;
export type Location = 1 | 2 | 3;
export type RequestInventoryStatus = 1 | 2 | 3 | 4;
export type Warehouse = 1 | 2;
}
declare namespace App.Enums.Menu {
export type Group = 1 | 2 | 3 | 4 | 5 | 6 | 7;
}
declare namespace App.Enums.Production {
export type Classification = 's' | 'a' | 'b' | 'c' | 'd';
export type EquipmentType = 'lasika' | 'others';
export type EventType = 'wedding' | 'engagement' | 'event' | 'birthday' | 'concert' | 'corporate' | 'exhibition';
export type ProjectDealChangePriceStatus = 1 | 2 | 3;
export type ProjectDealChangeStatus = 1 | 2 | 3;
export type ProjectDealStatus = 0 | 1 | 2 | 3;
export type ProjectStatus = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;
export type ProjectTaskAttachment = 1 | 2 | 3;
export type RequestEquipmentStatus = 1 | 2 | 3 | 4 | 5 | 6 | 7;
export type ShowreelsStatus = 1 | 2;
export type TaskHistoryType = 'cross_team_collaboration' | 'single_assignee' | 'temporary_transfer' | 'split_assignment';
export type TaskPicStatus = 1 | 2 | 3 | 4;
export type TaskSongStatus = 1 | 2 | 3 | 4 | 5 | 6;
export type TaskStatus = 1 | 2 | 3 | 4 | 5 | 6 | 7;
export type TaskType = 'asset3d' | 'compositing' | 'animating' | 'finalize';
export type TransferTeamStatus = 1 | 2 | 3 | 4 | 5 | 6;
export type WorkType = 'on_progress' | 'assigned' | 'check_by_pm' | 'revise' | 'finish' | 'on_hold';
}
declare namespace App.Enums.Production.Entertainment {
export type TaskSongLogType = 'assignJob' | 'approved' | 'completed' | 'checkByPM' | 'approvedByPM' | 'reviseByPM' | 'checkByPMProject' | 'jobCompleted' | 'reviseByPMProject' | 'delegateByPM' | 'changePICByPM' | 'approveRequestEdit' | 'rejectRequestEdit' | 'removeWorkerFromTask' | 'requestToEditSong' | 'editSong' | 'startWorking' | 'reportAsDone' | 'approveByEntertaimentPM' | 'approveByEventPM';
}
declare namespace App.Enums.System {
export type BaseRole = 'project manager' | 'root' | 'marketing' | 'director' | 'production' | 'entertainment' | 'project manager admin' | 'it support' | 'hrd' | 'finance' | 'regulare employee' | 'assistant manager' | 'project manager entertainment' | 'lead modeller' | 'sales';
}
declare namespace App.Enums.Telegram {
export type ChatStatus = 1 | 2 | 3;
export type ChatType = 'free_text' | 'bot_command';
export type CommandList = 'connection' | 'my_task';
}
declare namespace App.Enums.Transaction {
export type InvoiceStatus = 1 | 2 | 3;
export type TransactionType = 'down_payment' | 'credit' | 'repayment' | 'refund';
}
declare namespace Modules.Email.Data.Notification {
export type SlackTableHeaderColumnData = {
type: string;
elements: Array<Modules.Email.Data.Notification.SlackTableHeaderSectionData>;
};
export type SlackTableHeaderElementData = {
type: string;
text: string;
style: Modules.Email.Data.Notification.SlackTableHeaderStyleData;
};
export type SlackTableHeaderSectionData = {
type: string;
elements: Array<Modules.Email.Data.Notification.SlackTableHeaderElementData>;
};
export type SlackTableHeaderStyleData = {
bold: boolean;
};
export type SlackTablePayloadData = {
payload: Array<Modules.Email.Data.Notification.SlackTableHeaderColumnData>;
};
}
