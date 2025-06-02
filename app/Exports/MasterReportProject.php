<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MasterReportProject implements WithMultipleSheets
{
    public function sheets(): array
    {
        $projects = \Modules\Production\Models\Project::selectRaw('id,name,project_date,country_id,state_id,city_id,venue,collaboration,status,project_class_id')
            ->with([
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,name,email,employee_id',
                'projectClass:id,name',
                'country:id,name',
                'state:id,name',
                'city:id,name',
                'tasks' => function ($query) {
                    $query->selectRaw('id,project_id')
                        ->with([
                            'times:id,project_task_id,employee_id,work_type,time_added'
                        ])
                        ->orderBy('id', 'asc');
                }
            ])
            ->whereNotNull('status')
            ->orderBy('project_date', 'desc')
            ->get();

        $projects = $projects->map(function ($item) {
            $firstTimeTaskAdded = '-';
            $lastTimeAdded = '-';

            if ($item->tasks->count() > 0) {
                if ($item->tasks[0]->times->count() > 0) {
                    $firstTimeTaskAdded = $item->tasks[0]->times[0]->time_added;
                }

                if ($item->tasks[$item->tasks->count() - 1]->times->count() > 0) {
                    $lastTimeAdded = $item->tasks[$item->tasks->count() - 1]->times[$item->tasks[$item->tasks->count() - 1]->times->count() - 1]->time_added;
                }
            }

            $item['lastTimeAdded'] = $lastTimeAdded;
            $item['firstTimeTaskAdded'] = $firstTimeTaskAdded;
            $item['year'] = date('Y', strtotime($item->project_date));
            $item['countryName'] = $item->country->name;
            $item['stateName'] = $item->state->name;
            $item['cityName'] = $item->city->name;

            return $item;
        });

        // grouped by region
        $groupedByRegion = $projects->groupBy('year')
            ->map(function ($mapping) {
                $mapping = $mapping->groupBy('countryName')
                    ->map(function ($country) {
                        $country = $country->groupBy('stateName')
                            ->map(function ($state) {
                                $state = $state->groupBy('cityName');

                                return $state;
                            });

                        return $country;
                    });

                return $mapping;
            });

        // grouped based on number of pic
        $singlePic = $projects->filter(function ($filter) {
            return $filter->personInCharges->count() == 1;
        })->values()->map(function ($item) {
            $item['personInChargeId'] = $item->personInCharges[0]->pic_id;
            $item['year'] = date('Y', strtotime($item->project_date));

            return $item;
        });
        $groupedSinglePic = $singlePic->groupBy('year')->map(function ($item) {
            $item = $item->groupBy('personInChargeId')->values();

            return $item;
        });

        // group single pic project based on project_class_id and project_date
        $groupedByClass = $projects->filter(function ($filter) {
            return $filter->personInCharges->count() == 1;
        })
        ->values()
        ->map(function ($item) {
            $item['year'] = date('Y', strtotime($item->project_date));
            $item['personInChargeId'] = $item->personInCharges[0]->employee->name;
            $item['projectClassName'] = $item->projectClass->name;

            return $item;
        })->groupBy('year')
        ->map(function($item) {
            $item = $item->groupBy('personInChargeId')
                ->map(function ($itemDetail) {
                    $itemDetail = $itemDetail->groupBy('projectClassName');

                    return $itemDetail;
                });

            return $item;
        });

        $multiplePic = $projects->filter(function ($filter) {
            return $filter->personInCharges->count() > 1;
        })->values();

        $eastJava = \Modules\Company\Models\State::where('name', 'jawa timur')
            ->first();

        $eastJavaProject = $singlePic->filter(function($filter) use ($eastJava) {
            return $filter->state_id == $eastJava->id;
        })->values();

        $sheets = [];

        foreach ($groupedSinglePic as $year => $value) {
            // grouped by year + pic
            $sheets[] = new \App\Exports\SinglePicProject($year, $value);
        }

        foreach ($groupedByClass as $year => $value) {
            $sheets[] = new \App\Exports\ReportSinglePicProjectGroupByClass($year, $value);
        }

        foreach ($groupedByRegion as $year => $region) {
            $sheets[] = new \App\Exports\ReportProjectByRegion($year, $region);
        }

        return $sheets;
    }
}
