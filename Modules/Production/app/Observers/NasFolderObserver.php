<?php

namespace Modules\Production\Observers;

use App\Enums\Production\ProjectStatus;
use App\Services\GeneralService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Production\Models\NasFolderCreation;
use Modules\Production\Models\NasFolderCreationBackup;
use Modules\Production\Models\Project;

class NasFolderObserver
{

    protected function folders()
    {
        return [
            "BRIEF",
            "ASSET_3D",
            "ASSET_FOOTAGE",
            "FINAL_RENDER",
            "ASSET_SEMENTARA",
            "PREVIEW",
            "SKETSA",
            "TC",
            "RAW",
            "AUDIO"
        ];
    }

    protected function minifyFolders()
    {
        return [
            "FINAL_RENDER",
            "PREVIEW",
            'RAW',
            'OLD'
        ];
    }

    protected function pregName(string $name)
    {
        return preg_replace('/[.,\"~@\/]/', '', $name);
    }

    protected function createFolderSchema(Project $customer, ?object $currentData = null): array
    {
        $name = $this->pregName(name: $customer->name);
        $name = stringToPascalSnakeCase($name);

        $date = date('d', strtotime($customer->project_date));
        $month = date('m', strtotime($customer->project_date));
        $monthText = MonthInBahasa(date('m', strtotime($customer->project_date)));
        $subFolder1 = strtoupper($month . '_' . $monthText);
        $prefixName = strtoupper($date . "_" . $monthText);

        $subFolder2 = $prefixName . '_' . $name;

        // get current folder name
        if ($currentData) {
            $prefixCurrentName = strtoupper($date . '_' . $monthText);
            $currentFolderName1 = strtoupper($month . '_' . $monthText);
            $currentName = stringToPascalSnakeCase($this->pregName(name: $currentData->project_name));
            $currentFolderName = $prefixCurrentName . '_' . $currentName;
        }

        $year = date('Y', strtotime($customer->project_date));

        $parent =  "/{$year}/{$subFolder1}/{$subFolder2}";

        $toBeCreatedParents = [];
        $toBeCreatedNames = [];
        foreach ($this->folders() as $folder) {
            $toBeCreatedParents[] = $parent;
            $toBeCreatedNames[] = $folder;
        }

        // set current path
        $currentPath = [];
        foreach ($toBeCreatedParents as $keyFolder => $folder) {
            $currentPath[] = $folder . "/" . $toBeCreatedNames[$keyFolder];
        }

        return [
            'folder_path' => $toBeCreatedParents,
            'last_folder_name' => $toBeCreatedNames,
            'current_path' => $currentPath,
            'updated_name' => $currentFolderName ?? NULL
        ];
    }

    public function buildPayload(object $customer): array
    {
        $name = $this->pregName(name: $customer->name);
        $name = stringToPascalSnakeCase($name);

        $date = date('d', strtotime($customer->project_date));
        $month = date('m', strtotime($customer->project_date));
        $monthText = MonthInBahasa(date('m', strtotime($customer->project_date)));
        $subFolder1 = strtoupper($month . '_' . $monthText);
        $prefixName = strtoupper($date . "_" . $monthText);

        $subFolder2 = $prefixName . '_' . $name;

        $year = date('Y', strtotime($customer->project_date));

        $parent =  "/{$year}/{$subFolder1}/{$subFolder2}";

        $toBeCreatedParents = [];
        $toBeCreatedNames = [];
        foreach ($this->folders() as $folder) {
            $toBeCreatedParents[] = $parent;
            $toBeCreatedNames[] = $folder;
        }

        // set current path
        $currentPath = [];
        foreach ($toBeCreatedParents as $keyFolder => $folder) {
            $currentPath[] = $folder . "/" . $toBeCreatedNames[$keyFolder];
        }

        $generalService = new GeneralService();
        $sharedFolder = $generalService->getSettingByKey('nas_current_root');

        return [
            'shared_folder' => $sharedFolder ?? 'shared-folder',
            'year' => $year,
            'month_name' => $subFolder1,
            'project_name' => $name,
            'prefix_project_name' => $prefixName,
            'child_folders' => $this->folders(),
            'project_id' => $customer->id,
        ];
    }

    /**
     * Handle the NasFolder "created" event.
     */
    public function created(Project $customer)
    {
        // running start from january
        if (date('m', strtotime($customer->project_date)) >= 1 && date('Y', strtotime($customer->project_date)) >= 2025) {
            NasFolderCreationBackup::create($this->buildPayload($customer));
        }
    }

    /**
     * If current project exists and status is active and type is create, just edit the path
     * Otherwise update status, type to update and path
     * Handle the NasFolder "updated" event.
     */
    public function updating(Project $project): void
    {
        // what are the triggers for this function to run?
        // 1. Changes in name
        // 2. Changes in date
        // 3. Changes in status

        // how to treat the different:
        // 1. Different in the date:
            // - current 'status' = 0 then create a one row with type is 'update'. Update or move 

        $payload = $this->buildPayload($project);

        $currentData = NasFolderCreationBackup::whereRaw("project_id = {$project->id} AND status IN (1,3)")
            ->first();

        if ($currentData) {
            if (
                $project->getOriginal('name') != $project->name ||
                $project->getOriginal('project_date') != $project->project_date ||
                $project->getOriginal('status') != $project->status
            ) {
                $inactiveStatus = [
                    ProjectStatus::Canceled->value,
                    ProjectStatus::Draft->value
                ];
                if (in_array($project->status, $inactiveStatus)) {
                    $payload['status'] = 0;
                }

                NasFolderCreationBackup::where('id', $currentData->id)
                    ->update($payload);
            }
        }
    }

    /**
     * Handle the NasFolder "deleted" event.
     */
    public function deleting(Project $project)
    {
        $check = NasFolderCreation::where('project_id', $project->id)
            ->latest()
            ->first();

        $schema = $this->createFolderSchema($project);

        if ($check) {
            if ($check->status == 0) { // already execute
                // activate again with status is delete
                NasFolderCreation::where('id', $check->id)
                    ->update([
                        'type' => 'delete',
                        'status' => 1
                    ]);
            } else if ($check->status > 0) {
                NasFolderCreation::where('id', $check->id)
                    ->delete();

                return NasFolderCreation::create([
                    'project_name' => $project->name,
                    'project_id' => $project->id,
                    'folder_path' => json_encode($schema['folder_path']),
                    'status' => 1,
                    'type' => 'delete',
                    'last_folder_name' => json_encode($schema['last_folder_name']),
                    'current_folder_name' => $project->name,
                    'current_path' => json_encode($schema['current_path'])
                ]);
            } else if (!$check) {
                return NasFolderCreation::create([
                    'project_name' => $project->name,
                    'project_id' => $project->id,
                    'folder_path' => json_encode($schema['folder_path']),
                    'status' => 1,
                    'type' => 'delete',
                    'last_folder_name' => json_encode($schema['last_folder_name']),
                    'current_folder_name' => $project->name,
                    'current_path' => json_encode($schema['current_path'])
                ]);
            }
        }
    }

    /**
     * Handle the NasFolder "restored" event.
     */
    public function restored(NasFolder $nasfolder): void
    {
        //
    }

    /**
     * Handle the NasFolder "force deleted" event.
     */
    public function forceDeleted(NasFolder $nasfolder): void
    {
        //
    }
}
