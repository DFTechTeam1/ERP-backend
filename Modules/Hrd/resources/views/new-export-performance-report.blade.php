@php
    // Defensive: keep the export working even if the period is not supplied (e.g. an old
    // queued job rendering this template after a deploy).
    $startDate = $startDate ?? null;
    $endDate = $endDate ?? null;
    $periodLabel = ($startDate && $endDate)
        ? ' — '.date('d M Y', strtotime($startDate)).' s/d '.date('d M Y', strtotime($endDate))
        : '';
@endphp
<table>
    <thead>
        <tr>
            <th colspan="11" style="font-weight: bold;">
                LAPORAN PERFORMA KARYAWAN{{ $periodLabel }}
            </th>
        </tr>
        <tr>
            <th style="font-weight: bold;">No</th>
            <th style="font-weight: bold;">Nama Event / Klien</th>
            <th style="font-weight: bold;">Nama PM / PIC</th>
            <th style="font-weight: bold;">Nama Karyawan</th>
            <th style="font-weight: bold;">Posisi</th>
            <th style="font-weight: bold;">Tugas</th>
            <th style="font-weight: bold;">Jumlah Tugas</th>
            <th style="font-weight: bold;">Poin Dasar</th>
            <th style="font-weight: bold;">Poin Tambahan</th>
            <th style="font-weight: bold;">Total Poin</th>
            <th style="font-weight: bold;">Feedback</th>
        </tr>
    </thead>
    <tbody>
        @php $projectNumber = 0; @endphp
        @foreach($points as $projectName => $projectRows)
            @foreach($projectRows as $rowIndex => $row)
            <tr>
                @if($rowIndex === 0)
                <td rowspan="{{ count($projectRows) }}">{{ $projectNumber + 1 }}</td>
                <td rowspan="{{ count($projectRows) }}">{{ $projectName }}</td>
                <td rowspan="{{ count($projectRows) }}">{{ $row['pics'] }}</td>
                @endif
                <td>{{ $row['employee_name'] }}</td>
                <td>{{ $row['position'] }}</td>
                <td>{{ $row['tasks'] }}</td>
                <td>{{ $row['total_tasks'] }}</td>
                <td>{{ $row['original_point'] }}</td>
                <td>{{ $row['additional_point'] }}</td>
                <td>{{ $row['total_point'] }}</td>
                @if($rowIndex === 0)
                <td rowspan="{{ count($projectRows) }}">
                    @foreach($row['feedbacks'] as $feedback)
                        {{ $feedback }}@if(! $loop->last)<br>@endif
                    @endforeach
                </td>
                @endif
            </tr>
            @endforeach
        @php $projectNumber++; @endphp
        @endforeach
    </tbody>
</table>
