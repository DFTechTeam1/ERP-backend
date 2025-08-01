<table>
    <thead>
        <tr>
            <th style="font-weight: bold; text-align: center;">No</th>
            <th style="font-weight: bold; text-align: center;">Status</th>
            <th style="font-weight: bold; text-align: center;">Marketing</th>
            <th style="font-weight: bold; text-align: center;">Tanggal</th>
            <th style="font-weight: bold; text-align: center;">Client</th>
            <th style="font-weight: bold; text-align: center;">Venue</th>
            <th style="font-weight: bold; text-align: center;">Kota</th>
            <th style="font-weight: bold; text-align: center;">EO</th>
            <th style="font-weight: bold; text-align: center;">Ukuran LED</th>
            <th style="font-weight: bold; text-align: center;">Fee</th>
            <th style="font-weight: bold; text-align: center;">Pembayaran DP</th>
            <th style="font-weight: bold; text-align: center;">Tgl Pembayaran DP</th>
            <th style="font-weight: bold; text-align: center;">Pelunasan</th>
            <th style="font-weight: bold; text-align: center;">Tgl Lunas</th>
            <th style="font-weight: bold; text-align: center;">Refund</th>
            <th style="font-weight: bold; text-align: center;">Tanggal Refund</th>
            <th style="font-weight: bold; text-align: center;">Keterangan</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($projects as $key => $project)
            <tr>
                <td>{{ $key + 1 }}</td>
                <td>Confirm</td>
                <td>{{ $project->marketing_name }}</td>
                <td>{{ $project->project_date }}</td>
                <td>{{ $project->name }}</td>
                <td>{{ $project->venue }}</td>
                <td>{{ $project->city->name }}</td>
                <td>{{ $project->collaboration }}</td>
                <td>{{ $project->led_area }}</td>
                <td>Rp{{ number_format(num: $project->finalQuotation->fix_price, decimal_separator: ',') }}</td>
                <td>
                    @if ($project->down_payment)
                        Rp{{ number_format(num: $project->down_payment, decimal_separator: ',') }}
                    @else

                    @endif
                </td>
                <td>{{ $project->down_payment_date }}</td>
                <td>
                    @if ($project->repayment)
                        Rp{{ number_format(num: $project->repayment, decimal_separator: ',') }}
                    @else

                    @endif
                </td>
                <td>{{ $project->repayment_date }}</td>
                <td>
                    @if ($project->refund)
                        Rp{{ number_format(num: $project->refund, decimal_separator: ',') }}
                    @else

                    @endif
                </td>
                <td>{{ $project->refund_date }}</td>
                <td></td>
            </tr>
        @endforeach
    </tbody>
</table>