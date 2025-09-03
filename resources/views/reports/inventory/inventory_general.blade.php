<table>
    <tbody>
        <tr></tr> <!-- 1 -->
        <tr></tr> <!-- 2 -->
        <tr>
            <td></td> <!-- A -->
            <td colspan="2">Per Brands</td> <!-- B -->
        </tr>

        <tr>
            <td></td> <!-- A -->
            <td style="font-weight: bold;">Name</td> <!-- B -->
            <td style="font-weight: bold;">Total Items</td> <!-- B -->
            <td style="font-weight: bold;">Total Price</td> <!-- B -->
        </tr>

        @foreach ($data['per_brand'] as $brandName => $item)
            <tr>
                <td></td> <!-- A -->
                <td>{{ $brandName }}</td> <!-- B -->
                <td>{{ $data['per_brand'][$brandName]['total_item'] }}</td> <!-- B -->
                <td>{{ $data['per_brand'][$brandName]['total_price'] }}</td> <!-- B -->
            </tr>
        @endforeach
    </tbody>
</table>