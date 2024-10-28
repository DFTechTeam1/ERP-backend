<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Barcodes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .page {
            width: 210mm; /* A4 width */
            height: 297mm; /* A4 height */
            padding: 10mm;
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            grid-gap: 5mm;
            page-break-after: always;
        }
        .barcode {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid #000;
            padding: 5px;
            height: 20px;
            font-size: 12px;
            text-align: center;
        }
        .barcode img {
            max-width: 100%;
            max-height: 80%;
            margin-bottom: 5px;
        }
        @media print {
            .page {
                padding: 0;
                margin: 0;
                width: 100%;
                height: auto;
                grid-gap: 0;
            }
        }
    </style>
</head>
<body>
<div class="page">
    @foreach($data as $barcode)
        <div class="barcode">
            <img src="{{ $barcode['barcode_path'] }}" alt="Barcode">
            <span>{{ $barcode['build_series'] }}</span>
        </div>
    @endforeach
</div>
</body>
</html>
