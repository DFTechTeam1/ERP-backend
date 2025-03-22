<table>
    <thead>
        <tr>
            <th style="font-weight: bold;">No</th>
            <th style="font-weight: bold;">Nama Event / Klien</th>
            <th style="font-weight: bold;">Nama PM / PIC</th>
            <th style="font-weight: bold;">Nama Karyawan</th>
            <th style="font-weight: bold;">Tugas</th>
            <th style="font-weight: bold;">Poin</th>
            <th style="font-weight: bold;">Detail Pekerjaan</th>
        </tr>
    </thead>
    <tbody>
        @php $key = 0; @endphp
        @foreach($points as $projectName => $points)
            @foreach($points as $keyPoint => $point)
            <tr>
                @if($keyPoint == 0)
                <td rowspan="{{ count($points) }}">{{ $key + 1 }}</td>
                @endif
                @if($keyPoint == 0)
                <td rowspan="{{ count($points) }}">{{ $projectName }}</td>
                @endif
                <td>-</td>
                <td>{{ $point['employee_name'] }}</td>
                <td>{{ $point['position'] }}</td>
                <td>{{ $point['total_point'] }}</td>
                <td>{{ $point['tasks'] }}</td>
            </tr>
            @endforeach
        @php $key++ @endphp
        @endforeach
    </tbody>
</table>
