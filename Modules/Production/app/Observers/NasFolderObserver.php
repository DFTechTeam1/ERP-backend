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

    protected function createFolderSchema(Project $customer): array
    {
        $name = preg_replace('/[.,\"~@\/]/', '', $customer->name);
        $name = stringToPascalSnakeCase($name);

        $month = date('m', strtotime($customer->project_date));
        $monthText = MonthInBahasa(date('m', strtotime($customer->project_date)));
        $subFolder1 = strtoupper($month . '_' . $monthText);

        $subFolder2 = $subFolder1 . '_' . $name;

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
        ];
    }

    /**
     * Handle the NasFolder "created" event.
     */
    public function created(Project $customer)
    {
        Log::debug('created', $customer->toArray());

        $schema = $this->createFolderSchema($customer);

        NasFolderCreation::create([
            'project_name' => $customer->name,
            'project_id' => $customer->id,
            'folder_path' => json_encode($schema['folder_path']),
            'status' => 1,
            'type' => 'create',
            'last_folder_name' => json_encode($schema['last_folder_name']),
            'current_folder_name' => $customer->name,
            'current_path' => json_encode($schema['current_path'])
        ]);

        echo json_encode($schema);
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
            ->first();

        $schema = $this->createFolderSchema($customer);
        if ($check) { // When queue already exists
            if ($check->project_name != $customer->name) { // if there have different name between request data and existing data
                if ($check->status == 0 || $check->status == 3) { // update only when queue status id 0 (Inactive) and 3 (Failed)
                    // set current path from existing path
                    $currentPathExisting = [];
                    $folderPath = json_decode($check->folder_path, true);
                    $names = json_decode($check->last_folder_name, true);
                    foreach ($folderPath as $keyFd => $fd) {
                        $currentPathExisting[] = $fd . "/" . $names[$keyFd];
                    }

                    $check->folder_path = json_encode($schema['folder_path']);
                    $check->last_folder_name = json_encode($schema['last_folder_name']);
                    $check->type = 'update';
                    $check->status = 1;
                    $check->current_path = json_encode($currentPathExisting);
                    $check->save();
                }
            }
        } else { // when there's no record
            $this->created($customer);
        }
    }

    /**
     * Handle the NasFolder "deleted" event.
     */
    public function deleted(Project $project): void
    {
        Log::debug('deleted project: ', $project->toArray());
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
