<table>
    <thead>
        <tr>
            <th style="font-weight: bold;">Employee ID</th>
            <th style="font-weight: bold;">Name</th>
            <th style="font-weight: bold;">Position</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($all as $employee)
            <tr>
                <td>{{ $employee['name'] }}</td>
                <td>{{ $employee['employee_id'] }}</td>
                <td>{{ $employee['new_position']['name'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>