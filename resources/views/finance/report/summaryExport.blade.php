<table>
    <thead>
        <tr>
            <th rowspan="2">Event Date</th>
            <th rowspan="2">Event Name</th>
            <th rowspan="2">Venue</th>
            <th rowspan="2">Status Event (Deal)</th>
            <th rowspan="2">Status Payment</th>
            <th rowspan="2">Marketing</th>
            <th rowspan="2">Fix Price</th>
            <th colspan="2" style="text-align: center;">Transactions</th>
        </tr>
        <tr>
            <th>Payment Amount</th>
            <th>Payment Date</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($projects as $project)
            @php
                $numberOfTransactions = $project->transactions->count();
                $numberOfTransactions = $numberOfTransactions == 0 ? 1 : $numberOfTransactions;
            @endphp
            <tr>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle" rowspan="{{ $numberOfTransactions }}">{{ $project->projectDateFormat }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle" rowspan="{{ $numberOfTransactions }}">{{ $project->name }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle" rowspan="{{ $numberOfTransactions }}">{{ $project->venue }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle" rowspan="{{ $numberOfTransactions }}">{{ $project->status->label() }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle" rowspan="{{ $numberOfTransactions }}">{{ $project->status_payment }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle" rowspan="{{ $numberOfTransactions }}">{{ $project->marketingName }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle" rowspan="{{ $numberOfTransactions }}">{{ $project->finalQuotation ? number_format(num: $project->finalQuotation->fix_price, decimal_separator: ',') : '-' }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle">{{ $project->transactions->count() > 0 ? "Rp" . number_format(num: $project->transactions[0]->payment_amount, decimal_separator: ',') : '-' }}</td>
                <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle">{{ $project->transactions->count() > 0 ? date('d F Y', strtotime($project->transactions[0]->transaction_date)) : '-' }}</td>
            </tr>
            
            @foreach ($project->other_transactions as $otherTrx)
                <tr>
                    <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle">Rp{{ number_format(num: $otherTrx->payment_amount, decimal_separator: ',') }}</td>
                    <td @if($project->is_final) bgcolor="#66FF66" @endif valign="middle">{{ date('d F Y', strtotime($otherTrx->transaction_date)) }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>