<table>
    <thead>
        <tr style="font-weight: bold; font-size: 16px;">
            <th>PIC</th>
            <th>Project Date</th>
            <th>Name</th>
            <th>Class</th>
            <th>Country</th>
            <th>State</th>
            <th>City</th>
        </tr>
    </thead>

    <tbody>
       @foreach($projects as $group)
            @foreach($group as $index => $project)
                <tr>
                    @if($index === 0)
                        <td rowspan="{{ count($group) }}">{{ $project->personInCharges[0]->employee->name }}</td>
                    @endif
                    <td>{{ \Carbon\Carbon::parse($project->project_date)->format('d M Y') }}</td>
                    <td>{{ $project->name }}</td>
                    <td>{{ $project->projectClass ? $project->projectClass->name : '-' }}</td>
                    <td>{{ $project->country->name }}</td>
                    <td>{{ $project->state->name }}</td>
                    <td>{{ $project->city->name }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>