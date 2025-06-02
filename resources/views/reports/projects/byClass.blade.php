<table class="table table-bordered table-hover">
    <thead class="thead-light">
        <tr>
            <th>Name</th>
            <th>Project Class</th>
            <th>Project Name</th>
            <th>Project Date</th>
            <th>First time task Added</th>
            <th>Last time task Updated</th>
            <th>Venue</th>
            <th>Location</th>
        </tr>
    </thead>
    <tbody>
        @foreach($projects as $picName => $projectClasses)
            @php
                $totalProjectsForPic = 0;
                foreach($projectClasses as $classProjects) {
                    $totalProjectsForPic += count($classProjects);
                }
            @endphp
            
            @foreach($projectClasses as $className => $projectsInClass)
                @foreach($projectsInClass as $index => $project)
                    <tr>
                        @if($loop->parent->first && $loop->first)
                            <td rowspan="{{ $totalProjectsForPic }}" style="vertical-align: middle;">{{ $picName }}</td>
                        @endif
                        
                        @if($loop->first)
                            <td rowspan="{{ count($projectsInClass) }}" style="vertical-align: middle;">
                                {{ $className }}
                            </td>
                        @endif
                        
                        <td>{{ $project->name }}</td>
                        <td>{{ \Carbon\Carbon::parse($project->project_date)->format('d M Y') }}</td>
                        <td>{{ $project->firstTimeTaskAdded }}</td>
                        <td>{{ $project->lastTimeAdded }}</td>
                        <td>{{ $project->venue }}</td>
                        <td>{{ $project->city->name }}, {{ $project->state->name }}, {{ $project->country->name }}</td>
                    </tr>
                @endforeach
            @endforeach
        @endforeach
    </tbody>
</table>