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
        }

        .header-item tbody tr td {
            padding: 30px 20px;
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
            padding: 10px 20px;
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

        .product {
            width: 100%;
            margin-top: 30px;
        }

        .product thead tr th {
            color: #b8b6b6;
            text-transform: uppercase;
            padding: 10px 20px;
            font-size: 13px;
            border-bottom: 1px solid #b8b6b6;
        }

        .product thead tr th:first-child {
            width: 60%;
        }

        .product thead tr th:nth-child(2),
        .product thead tr th:nth-child(3) {
            width: 20%;
            text-align: right;
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
                            <p>The Wedding Reception of Mr. Adhit & Ms. Tiffany - 14 Juni 2024 - MCC - Semarang</p>
                        </td>
                        <td>
                            <div class="header-item-amount">
                                <p>Amount Due (IDR)</p>
                                <p>Rp90,000,000</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="addressing">
                <tbody>
                    <tr>
                        <td class="customer">
                            <p>Bill To</p>
                            <p>Topeng EO</p>
                            <p>Semarang,</p>
                            <p>Indonesia</p>
                        </td>
                        <td class="summary">
                            <table class="summary-table">
                                <tr>
                                    <td>Invoice Number</td>
                                    <td>:</td>
                                    <td>v/2025 - 937</td>
                                </tr>
                                <tr>
                                    <td>Invoice Date</td>
                                    <td>:</td>
                                    <td>May 9, 2025</td>
                                </tr>
                                <tr>
                                    <td>Payment Due</td>
                                    <td>:</td>
                                    <td>May 24, 2025</td>
                                </tr>
                                <tr>
                                    <td>Amount Due (IDR)</td>
                                    <td>:</td>
                                    <td>Rp90,000,000</td>
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
                        
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>