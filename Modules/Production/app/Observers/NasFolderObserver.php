<?php

namespace Modules\Production\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Production\Models\NasFolderCreation;
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

    protected function createFolderSchema(Project $customer, object $currentData = null): array
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

    /**
     * Handle the NasFolder "created" event.
     */
    public function created(Project $customer)
    {
        Log::debug('created', $customer->toArray());

        $schema = $this->createFolderSchema($customer);

        // running start from january
        if (date('m', strtotime($customer->project_date)) >= 1 && date('Y', strtotime($customer->project_date)) >= 2025) {
            NasFolderCreation::create([
                'project_name' => $customer->name,
                'project_id' => $customer->id,
                'folder_path' => json_encode($schema['folder_path']),
                'status' => 1,
                'type' => 'create',
                'last_folder_name' => json_encode($schema['last_folder_name']),
                'current_folder_name' => NULL,
                'current_path' => json_encode($schema['current_path'])
            ]);
        }
    }

    /**
     * If current project exists and status is active and type is create, just edit the path
     * Otherwise update status, type to update and path
     * Handle the NasFolder "updated" event.
     */
    public function updated(Project $customer): void
    {
        Log::debug("updated project: ", $customer->toArray());

        // check queue
        $check = NasFolderCreation::selectRaw('*')
            ->byProject($customer->id)
            ->latest()
            ->first();

        $schema = $this->createFolderSchema($customer, $check);
        if ($check) { // When queue already exists
            if ($check->project_name != $customer->name) { // if there have different name between request data and existing data
                if ($check->type != 'delete') { // update only when queue status id 1 (active) and 3 (Failed)
                    // set current path from existing path
                    $currentPathExisting = [];
                    $folderPath = json_decode($check->folder_path, true);
                    $names = json_decode($check->last_folder_name, true);
                    foreach ($folderPath as $keyFd => $fd) {
                        $currentPathExisting[] = $fd . "/" . $names[$keyFd];
                    }

                    $check->project_name = $customer->name;
                    $check->folder_path = json_encode($schema['folder_path']);
                    $check->last_folder_name = json_encode($schema['last_folder_name']);
                    $check->type = 'update';
                    $check->status = 1;
                    $check->current_folder_name = $schema['updated_name'];
                    $check->current_path = json_encode($currentPathExisting);
                    $check->save();
                }
            }
        }
    }

    /**
     * Handle the NasFolder "deleted" event.
     */
    public function deleting(Project $project)
    {
        Log::debug('deleted project: ', $project->toArray());
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
