<table>
    <thead>
        <tr>
            <th></th>
            <th>Name</th>
            <th>Code</th>
            <th>Brand</th>
            <th>Supplier</th>
            <th>Purchase Price</th>
            <th>Year of Purchase</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalPrice = 0;
        @endphp
        @foreach ($data['inventories'] as $item)
            <tr>
                <td></td>
                <td>{{ $item['name'] }}</td>
                <td>{{ $item['code'] }}</td>
                <td>{{ $item['brand'] }}</td>
                <td>{{ $item['supplier'] }}</td>
                <td>{{ $item['purchase_price'] }}</td>
                <td>{{ $item['year_of_purchase'] }}</td>
            </tr>

            @php
                $totalPrice += $item['purchase_price_raw'];
            @endphp
        @endforeach
        <tr>
            <td></td>
            <td style="font-size: 16px; font-weight: bold;" colspan="3">TOTAL</td>
            <td></td>
            <td style="font-size: 16px; font-weight: bold;">{{ "Rp" . number_format($totalPrice, 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>