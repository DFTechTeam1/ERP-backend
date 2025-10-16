<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Quotation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap" rel="stylesheet">

    <style>
        html {
            margin: 0;
        }

        body,table,p,span,ul,li,td {
            font-family: "Lato", sans-serif;
            font-weight: 400;
            font-style: normal;
            position: relative;
        }

        body {
            /* font-family: 'My Font', sans-serif; */
            position: relative;
            margin: 0;
            padding: 0;
        }

        .main-wrapper {
            width: 100%;
            border-collapse: collapse;
            /* Prevent page breaks inside tables */
            page-break-inside: avoid;
            /* Keep tables together */
            break-inside: avoid;
        }

        .page-break {
            page-break-after: always;
        }
        .no-break {
            page-break-inside: avoid;
        }

        .header-item {
            width: 100%;
            border-collapse: collapse;
        }

        .header-item tbody tr td {
            padding: 40px 30px;
        }
        .header-item tbody tr td:first-child {
            background-color: #000;
            width: 70% !important;
        }
        .header-item tbody tr td:last-child {
            width: 30% !important;
            background-color: #2b2b2a;
        }
        .header-item-title p:first-child {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #fff;
            width: 50%;
        }
        .header-item-title p:last-child {
            font-size: 16px;
            margin: 0;
            color: #fff;
            wrap: break-word;
            width: 100%;
        }

        .header-item-amount p {
            color: #fff;
        }

        .header-item-amount p:first-child {
            font-size: 14px;
            margin: 0;
            text-align: center;
        }

        .header-item-amount p:last-child {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            text-align: center;
        }

        .addressing {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .addressing tbody tr td:first-child {
            padding: 10px 30px;
            width: 50%;
            text-align: left
        }

        .addressing tbody tr td:first-child p {
            margin: 0;
        }

        .addressing tbody tr td:first-child p:first-child {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            color: #b8b6b6;
        }

        .addressing tbody tr td:first-child p:nth-child(2) {
            font-weight: bold;
        }

        .addressing tbody tr td:first-child p:nth-child(3),
        .addressing tbody tr td:first-child p:nth-child(4) {
            font-size: 13px;
        }

        .summary-table {
            width: 100%;
        }

        .summary-table tr td {
            padding: 4px !important;
            font-size: 13px;
        }

        .summary-table tr td:first-child {
            text-align: right !important;
            font-weight: bold
        }

        .summary-table tr td:nth-child(2) {
            text-align: center !important;
            width: 5px !important;
        }

        .summary-table tr td:nth-child(3) {
            text-align: left !important;
            padding-left: 12px !important;
        }

        .product,
        .total {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }

        .product thead tr th,
        .total tbody tr td {
            color: #b8b6b6;
            text-transform: uppercase;
            padding: 10px 30px;
            font-size: 13px;
            border-bottom: 1px solid #b8b6b6;
        }

        .product thead tr th:first-child,
        .total tbody tr td:first-child {
            width: 60%;
            text-align: left;
        }

        .product thead tr th:nth-child(2),
        .product thead tr th:nth-child(3),
        .total tbody tr td:nth-child(2),
        .total tbody tr td:nth-child(3) {
            width: 20%;
            text-align: right;
        }

        .product tbody tr td {
            padding: 10px 30px;
            border-bottom: 1px solid #b8b6b6;
        }

        .product .list-products p {
            margin: 0;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .product tbody tr td:nth-child(2),
        .product tbody tr td:nth-child(3) {
            text-align: right;
            align-items: baseline;
            font-weight: bold;
            font-size: 16px;
        }

        .footer {
            position: absolute;
            bottom: 0;
        }

        .footer-section {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-section tbody tr td:nth-child(1) {
            width: 20%;
            padding: 20px 30px;
            border-top: 1px solid #b8b6b6;
        }
        
        .footer-section tbody tr td:nth-child(2) {
            width: 30%;
            vertical-align: top;
            padding: 20px;
            border-top: 1px solid #b8b6b6;
        }
        
        .footer-section tbody tr td:nth-child(2) p,
        .footer-section tbody tr td:nth-child(3) p {
            margin: 0;
            font-size: 13px;
        }

        .footer-section tbody tr td:nth-child(2) p:first-child,
        .footer-section tbody tr td:nth-child(3) p:first-child {
            font-weight: bold;
        }
        
        .footer-section tbody tr td:nth-child(3) {
            width: 50%;
            vertical-align: top;
            padding: 20px;
            border-top: 1px solid #b8b6b6;
            text-align: right;
        }

        .footer-section .footer-img-wrapper img {
            width: 200px;
        }

        .terms {
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div class="header">
            <table class="header-item">
                <tbody>
                    <tr>
                        <td class="header-item-title">
                            <p>Service Invoice</p>
                            <p>{{ $projectName }} - {{ $projectDate }} - {{ $venue }}</p>
                        </td>
                        <td>
                            <div class="header-item-amount">
                                <p>Amount Due (IDR)</p>
                                <p>{{ $remainingPayment }}</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <table class="addressing">
            <tbody>
                <tr>
                    <td class="customer">
                        <p>Bill To</p>
                        <p>{{ $customer['name'] }}</p>
                        <p>{{ $customer['city'] }},</p>
                        <p>{{ $customer['country'] }}</p>
                    </td>
                    <td class="summary">
                        <table class="summary-table">
                            <tr>
                                <td>Invoice Number</td>
                                <td>:</td>
                                <td>{{ $invoiceNumber }}</td>
                            </tr>
                            <tr>
                                <td>Invoice Date</td>
                                <td>:</td>
                                <td>{{ $trxDate }}</td>
                            </tr>
                            <tr>
                                <td>Payment Due</td>
                                <td>:</td>
                                <td>{{ $paymentDue }}</td>
                            </tr>
                            <tr>
                                <td>Amount Due (IDR)</td>
                                <td>:</td>
                                <td>{{ $remainingPayment }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="product">
            <thead>
                <tr>
                    <th>Services</th>
                    <th>Rate</th>
                    <th>Amount</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>
                        <div class="list-products">
                            <p id="type-payment" style="margin-bottom: 8px; font-weight: bold;">Pembayaran</p>
                            <p>LED Visual Content</p>
                            <p>Content Media Size:</p>

                            @foreach ($led['main'] as $main)
                                <p>{{ $main['name'] }}: {{ $main['size'] }}</p>
                            @endforeach
                            @foreach ($led['prefunction'] as $prefunction)
                                <p>{{ $prefunction['name'] }}: {{ $prefunction['size'] }}</p>
                            @endforeach

                            <p style="margin-top: 16px;">Premium LED Visual</p>
                            <ul style="padding-left: 15px; padding-top: 5px; margin-top: 0; font-size: 13px;">
                                @foreach ($items as $item)
                                    <li style="font-size: 13px;">{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </td>
                    <td style="vertical-align: top;">
                        {{ $payment }}
                    </td>
                    <td style="vertical-align: top;">
                        {{ $payment }}
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="total">
            <tbody>
                <tr>
                    <td style="border-bottom: unset; width: 30%;"></td>
                    <td style="width: 22%; color: #353434;">Total</td>
                    <td style="width: 22%; color: #353434;">{{ $payment }}</td>
                </tr>
                @foreach ($transactions as $transaction)
                    <tr>
                        <td style="border-bottom: unset; width: 30%;"></td>
                        <td style="width: 22%; color: #353434; font-size: 12px;">Payment on {{ $transaction['transaction_date'] }}</td>
                        <td style="width: 22%; color: #353434;">{{ $transaction['payment'] }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="border-bottom: unset; width: 30%;"></td>
                    <td style="width: 22%; border-bottom: unset; font-weight: bold; color: #181717;">Amount Due (IDR)</td>
                    <td style="width: 22%; border-bottom: unset; font-weight: bold; color: #181717;">{{ $remainingPayment }}</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div class="footer-wrapper">
                <table class="footer-section">
                    <tbody>
                        <tr>
                            <td>
                                <div class="footer-img-wrapper">
                                    <img src="{{ public_path() . '/df-logo.png' }}" alt="Dfactory">
                                </div>
                            </td>
                            <td>
                                <div>
                                    <p>{{ $company['name'] }}</p>
                                    <p>{{ $company['address'] }}</p>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <p>Contact Information</p>
                                    <p>Mobile: {{ $company['phone'] }}</p>
                                    <p>{{ $company['email'] }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="page-break"></div>

        <div class="header">
            <table class="header-item">
                <tbody>
                    <tr>
                        <td class="header-item-title">
                            <p>Service Invoice</p>
                            <p>{{ $projectName }} - {{ $projectDate }} - {{ $venue }}</p>
                        </td>
                        <td>
                            <div class="header-item-amount">
                                <p>Amount Due (IDR)</p>
                                <p>{{ $remainingPayment }}</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <table class="terms">
            <tbody>
                <tr>
                    <td style="padding: 20px 30px;">
                        <p style="font-size: 14px; font-weight: bold; margin: 0 0 10px 0;">Notes / Terms</p>
                        <ul style="line-height: 1.5; font-size: 13px; list-style: circle; margin-left: -25px;">
                            <li>Pelunasan dilakukan maksimal H-3 sebelum event dilangsungkan.</li>
                            <li>Pembayaran melalui transfer ke rekening BCA 188 060 1225 a.n Wesley Wiyadi / Edwin Chandra Wijaya.</li>
                            {{-- <li>Biaya diatas hanya biaya layanan dan tidak termasuk biaya transportasi & akomodasi bila dibutuhkan.</li> --}}
                            <li>Biaya diatas tidak termasuk sistem multimedia, apabila dibutuhkan akan dikenakan biaya tambahan.</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div class="footer-wrapper">
                <table class="footer-section">
                    <tbody>
                        <tr>
                            <td>
                                <div class="footer-img-wrapper">
                                    <img src="{{ public_path() . '/df-logo.png' }}" alt="Dfactory">
                                </div>
                            </td>
                            <td>
                                <div>
                                    <p>{{ $company['name'] }}</p>
                                    <p>{{ $company['address'] }}</p>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <p>Contact Information</p>
                                    <p>Mobile: {{ $company['phone'] }}</p>
                                    <p>{{ $company['email'] }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>