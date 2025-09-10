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
        @foreach ($item['items'] as $inventory)
            <tr>
                <td></td>
                <td>{{ $inventory['name'] }}</td>
                <td>{{ $inventory['code'] }}</td>
                <td>{{ $inventory['brand'] }}</td>
                <td>{{ $inventory['supplier'] }}</td>
                <td>{{ $inventory['purchase_price'] }}</td>
                <td>{{ $inventory['year_of_purchase'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td></td>
            <td style="font-size: 16px; font-weight: bold;" colspan="3">TOTAL</td>
            <td></td>
            <td style="font-size: 16px; font-weight: bold;">{{ $item['total_price'] }}</td>
        </tr>
    </tbody>
</table>