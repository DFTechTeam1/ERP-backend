<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Styled Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: black;
            color: yellow;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        table {
            border-collapse: collapse;
            border: 2px solid yellow;
        }
        th, td {
            border: 1px solid yellow;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: black;
            color: yellow;
        }
        td {
            background-color: black;
            color: yellow;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th colspan="6" style="text-align: center; font-weight: bold;">
                    Performance Report {{ $startDate }} - {{ $endDate }}
                </th>
            </tr>
            <tr></tr>
            <tr></tr>
            <tr>
                <th style="font-weight: bold;">Name</th>
                <th style="font-weight: bold;">Employee ID</th>
                <th style="font-weight: bold;">Position</th>
                <th style="font-weight: bold;">Point</th>
                <th style="font-weight: bold;">Event</th>
                <th style="font-weight: bold;">Task</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($data as $item)
                @if ($item)
                    @if (count($item['detail_projects']) > 0)
                        @php
                            $event = $item; // Main event
                            $employee = $event->employee; 
                            $totalProjects = count($event->detail_projects);
                            $eventRowSpan = 0;

                            foreach ($event->detail_projects as $project) {
                                $eventRowSpan += count($project->tasks);
                            }
                        @endphp

                        @foreach ($event->detail_projects as $pIndex => $project)
                            @php
                                $totalTasks = count($project->tasks);
                                $projectRowSpan = $totalTasks;
                            @endphp

                            @foreach ($project->tasks as $tIndex => $task)
                                <tr>
                                    @if ($pIndex === 0 && $tIndex === 0)
                                        <td style="vertical-align: middle;" rowspan="{{ $eventRowSpan }}">{{ $employee->name }}</td>
                                        <td style="vertical-align: middle;" rowspan="{{ $eventRowSpan }}">{{ $employee->employee_id }}</td>
                                        <td style="vertical-align: middle;" rowspan="{{ $eventRowSpan }}">{{ $employee->position->name }}</td>
                                        <td style="vertical-align: middle;" rowspan="{{ $eventRowSpan }}">{{ $event->total_point }}</td>
                                    @endif

                                    @if ($tIndex === 0)
                                        <td rowspan="{{ $projectRowSpan }}">{{ $project['project']['name'] }}</td>
                                    @endif

                                    @if ($project->type == 'production')
                                        <td>{{ $task->productionTask->name }}</td>
                                    @else
                                        <td>{{ $task->entertainmentTask->song->name }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    @else
                        <tr>
                            <td>{{ $item['employee']->name }}</td>
                            <td>{{ $item['employee']->employee_id }}</td>
                            <td>{{ $item['employee']->position->name }}</td>
                            <td>0</td>
                            <td>-</td>
                            <td>-</td>
                        </tr>
                    @endif
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
