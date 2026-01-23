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
            <th></th>
            <th>Point Breakdown</th>
            <th>Feedbacks</th>
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
                <td>{{ $point['pics'] }}</td>
                <td>{{ $point['employee_name'] }}</td>
                <td>{{ $point['position'] }}</td>
                <td>{{ $point['total_point'] }}</td>
                <td>{{ $point['tasks'] }}</td>
                <td></td>
                <td>
                    <div>
                        <p>Is Prorate: ✅</p>
                        <p>Prorate Point: {{ $point['prorate_point'] }}</p>
                        <p>Original Point: {{ $point['original_point'] }}</p>
                        <p>Additional Point: {{ $point['additional_point'] }}</p>
                        <p>Calculated Prorate Point: {{ $point['calculated_prorate_point'] }}</p>
                        <p>Total Point: {{ $point['total_point'] }}</p>
                        <p>Total Tasks: {{ $point['total_tasks'] }}</p>
                    </div>
                </td>

                {{-- only put in the first line, then do rowspan --}}
                @if ($keyPoint == 0)
                <td rowspan="{{ count($points) }}">
                    <div>
                        @foreach($point['feedbacks'] as $feedback)
                            <p>{{ $feedback }}</p>
                        @endforeach
                    </div>
                </td>
                @endif
            </tr>
            @endforeach
        @php $key++ @endphp
        @endforeach
    </tbody>
</table>
