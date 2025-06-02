<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr>
            <th>Country</th>
            <th>State</th>
            <th>City</th>
            <th>Project Date</th>
            <th>Project Name</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($projects as $countryName => $countries)
            @foreach ($countries as $stateName => $states)
                @foreach ($states as $cityName => $cities)
                    @foreach ($cities as $project)
                        <tr>
                            <td>{{ $countryName }}</td>
                            <td>{{ $stateName }}</td>
                            <td>{{ $cityName }}</td>
                            <td>{{ date('d F Y', strtotime($project->project_date)) }}</td>
                            <td>{{ $project->name }}</td>
                            <td>{{ $project->status_text }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
        @endforeach
    </tbody>
</table>