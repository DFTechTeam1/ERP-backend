<?php

namespace Modules\Production\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Production\Models\NasFolderCreation;
use Modules\Production\Models\Project;

class NasFolderObserver
{
    /**
     * Handle the NasFolder "created" event.
     */
    public function created(Project $project): void
    {
        Log::debug('project', $project->toArray());
        $this->mainProcess($project->id, $project->name, $project->project_date);
    }

    protected function mainProcess(int $projectId, string $projectName, string $date, bool $isUpdate = false, int $nasId = 0)
    {
        $folders = getStructureNasFolder();

        // format name
        $year = date('Y', strtotime($date));
        $rawMonth = date('m', strtotime($date));
        $month = MonthInBahasa($rawMonth);
        $projectName = stringToPascalSnakeCase($projectName);
        $fixName = $rawMonth . "_" . $month . "_" . $projectName;

        $folders = collect($folders)->map(function ($mapping) use ($year, $fixName) {
            return str_replace(
                ["{year}", "{format_name}"],
                [$year, $fixName],
                $mapping
            );
        });

        // auto create folder creation request
        if ($isUpdate) {
            NasFolderCreation::where('id', $nasId)
                ->update([
                    'folder_path' => json_encode($folders)
                ]);
        } else {
            NasFolderCreation::create([
                'project_name' => $projectName,
                'project_id' => $projectId,
                'folder_path' => json_encode($folders)
            ]);
        }
    }

    /**
     * Handle the NasFolder "updated" event.
     */
    public function updated(Project $project): void
    {
        Log::debug("updated project: ", $project->toArray());

        $current = NasFolderCreation::select('id', 'project_name')
            ->where('project_id', $project->id)
            ->where('status', 1)
            ->first();
        if (($current) && $current->name != $project->name) {
            $this->mainProcess('0', $project->name, $project->project_date, true, $current->id);
        }
    }

    /**
     * Handle the NasFolder "deleted" event.
     */
    public function deleted(NasFolder $nasfolder): void
    {
        //
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
