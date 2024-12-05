<?php

namespace Modules\Production\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Production\Models\NasFolderCreation;
use Modules\Production\Models\Project;

class NasFolderObserver
{
    const StaticIP = '192.168.100.104';

    const Root = "queue_job_8";

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

        $parent = "/" . self::Root . "/{$year}/{$subFolder1}/{$subFolder2}";

        $toBeCreatedParents = [];
        $toBeCreatedNames = [];
        foreach ($this->folders() as $folder) {
            $toBeCreatedParents[] = $parent;
            $toBeCreatedNames[] = $folder;
        }

        return [
            'folder_path' => $toBeCreatedParents,
            'last_folder_name' => $toBeCreatedNames
        ];
    }

    /**
     * Handle the NasFolder "created" event.
     */
    public function created(Project $customer): void
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
            'current_folder_name' => $customer->name
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

        $current = NasFolderCreation::where('project_id', $customer->id)
            ->latest()
            ->first();

        $schema = $this->createFolderSchema($customer);
        if ($current->status && $current->type == 'create') {
            NasFolderCreation::where('project_id', $customer->id)
                ->update([
                    'folder_path' => json_encode($schema['folder_path']),
                    'last_folder_name' => json_encode($schema['last_folder_name']),
                ]);
        } else if (!$current->status) {
            NasFolderCreation::create([
                'project_name' => $customer->name,
                'project_id' => $customer->id,
                'folder_path' => json_encode($schema['folder_path']),
                'status' => 1,
                'type' => 'update',
                'last_folder_name' => json_encode($schema['last_folder_name']),
                'current_folder_name' => $customer->name
            ]);
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
