<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Quotation</title>

    <style>
        @font-face {
            font-family: 'My Font';
            font-style: normal;
            font-weight: normal;
            src: url({{ public_path('fonts/loto.ttf') }}) format('truetype');
            font-display: swap;
        }

        body,table,p,span,ul,li,td {
            font-family: "My Font", sans-serif;
            font-weight: 400;
            font-style: normal;
            position: relative;
        }

        :root {
            --left-width: 150px;
        }

        .main-wrapper {
            width: 100%;
            border-collapse: collapse;
            /* Prevent page breaks inside tables */
            page-break-inside: avoid;
            /* Keep tables together */
            break-inside: avoid;
        }

        /* Prevent orphans/widows in text */
        p, li, td {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .header .header-left {
            text-align: center;
            width: 70%;
            position: relative;
        }

        .header .header-left .header-left-content {
            width: var(--left-width);
        }

        .header .header-left img {
            width: 20px;
            height: auto;
        }

        .header .header-left .divider {
            width: 100%;
            margin: 20px 0;
            border-bottom: 1px solid #6a6969;
        }

        .header .header-right {
            width: 30%;
            text-align: right;
            align-content: baseline;
            position: relative;
        }

        .header .header-left .address-wrapper {
            text-align: left;
        }

        .header .header-left .address-wrapper p {
            font-size: 12px;
            margin: 0;
        }

        .header .header-left .footer {
            position: absolute;
            bottom: 0;
            text-align: center;
        }

        .header .header-left .footer img {
            width: 50px;
            height: auto;
        }

        .header-right table {
            width: 100%;
            position: absolute;
            top: 0;
        }

        .header-right table .quotation-number-title {
            font-size: 12px;
        }
        .header-right table .quotation-number-separator {
            font-size: 12px;
        }
        .header-right table .quotation-number-value {
            font-size: 12px;
        }
        
        .header-right table .quotation-number .first {
            font-weight: bold !important;
            font-size: 16px;
        }

        .header-right table .quotation-number td {
            padding-bottom: 4px;
        }
        
        .main-wrapper .main-content td {
            padding-left: calc(var(--left-width) + 10px);
        }

        .main-wrapper .main-content.addressing td {
            width: 100%;
        }

        .main-wrapper .addressing td:last-child {
            text-align: left;
        }

        .main-wrapper .addressing .box-addressing.to {
            border: 1px solid #000;
            padding: 10px;
            width: 100%;
            margin-top: -100px;
        }

        .main-wrapper .addressing .box-addressing.to p,
        .main-wrapper .addressing .box-addressing p {
            margin: 0;
            font-size: 13px;
        }

        .main-wrapper .addressing .box-addressing.to p.title,
        .main-wrapper .addressing .box-addressing p.title {
            font-weight: bold;
            font-family: 'Lato', sans-serif;
        }

        .main-wrapper .addressing .box-addressing.to p:nth-child(2),
        .main-wrapper .addressing .box-addressing p:nth-child(2) {
            margin-top: 20px;
        }

        .main-wrapper .addressing .box-addressing.from {
            width: 200px;
            margin-left: -80px;
            margin-top: -100px;
        }


        .main-wrapper .table-event,
        .main-wrapper .table-items,
        .main-wrapper .table-rules,
        .main-wrapper .conditions {
            width: 100%;
            padding-left: calc(var(--left-width) + 10px);
        }

        .main-wrapper .table-event.summary-event {
            font-size: 14px;
        }
        
        .main-wrapper .table-event tr:first-child td {
            padding-top: 20px;
        }

        .main-wrapper .table-event tr td:first-child {
            width: 20%;
            font-weight: bold;
            padding-bottom: 5px;
        }
        
        
        .main-wrapper .table-event tr td:nth-child(2) {
            width: 3% !important;
        }
        
        .main-wrapper .table-event tr td:nth-child(3) {
            width: 60% !important;
        }

        .main-wrapper .table-items {
            border-collapse: collapse;
            padding-top: 30px;
        }

        .main-wrapper .table-items thead tr th {
            text-align: left;
            font-size: 13px;
            padding: 5px;
            font-weight: bold;
            border: 1px solid #e6e6e6;
        }

        .main-wrapper .table-items thead tr th:last-child {
            text-align: center;
        }

        .main-wrapper .table-items tbody tr td {
            font-size: 13px;
            padding: 5px;
        }

        .main-wrapper .table-items tbody tr td p {
            margin: 0;
        }

        .main-wrapper .table-items tbody tr td:last-child {
            text-align: center;
            font-weight: bold;
            align-self: baseline;
        }   

        .main-wrapper .table-items tbody tr td:last-child span {
            align-content: baseline;
        }

        .page-break {
            page-break-after: always;
        }
        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="watermark" style="width: 470px; opacity: .1; position: absolute; top: 50%; left: 55%; transform: translate(-50%, -50%);">
        <img src="{{ public_path() . '/df-logo.png' }}" alt="watermark" style="width: 100%;">
    </div>
    <table class="main-wrapper">
        <tbody>
            <tr class="header">
                <td class="header-left">
                    <div class="header-left-content">
                        <img src="{{ public_path() . '/df-simple-black.png' }}" alt="Logo">
    
                        <div class="divider"></div>
    
                        <div class="address-wrapper">
                            <p>{{ $company['address'] }}</p>
                            <p>{{ $company['phone'] }}</p>
                            <p>{{ $company['email'] }}</p>
                            <table style="font-size: 12px;">
                                <tbody>
                                    <tr>
                                        <td style="padding-top: 10px;">
                                            <img src="{{ public_path() . '/images/instagram.png' }}" alt="instagram" style="width: 15px; height: auto;">
                                        </td>
                                        <td style="padding-top: 10px;">
                                            <span>@dfactory_</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
    
                        <div class="footer">
                            <img src="{{ public_path() . '/df-logo.png' }}" alt="Logo" style="width: 130px;">
                        </div>
                    </div>
                </td>
                <td class="header-right">
                    <table>
                        <tr class="quotation-number">
                            <td class="quotation-number-title first">Quotation No</td>
                            <td class="quotation-number-separator first">:</td>
                            <td class="quotation-number-value first">{{ $quotationNumber }}</td>
                        </tr>
                        <tr class="quotation-number">
                            <td class="quotation-number-title">Tanggal</td>
                            <td class="quotation-number-separator">:</td>
                            <td class="quotation-number-value">{{ $date }}</td>
                        </tr>
                        <tr class="quotation-number">
                            <td class="quotation-number-title">Design Job</td>
                            <td class="quotation-number-separator">:</td>
                            <td class="quotation-number-value">{{ $designJob }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="addressing main-content">
                <td>
                    <div class="box-addressing to">
                        <p class="title">Ditawarkan Kepada :</p>
                        <p>{{ $client['name'] }}</p>
                        <p>{{ $client['city'] }}</p>
                        <p>{{ $client['country'] }}</p>
                    </div>
                </td>
                <td>
                    <div class="box-addressing from">
                        <p class="title">Ditawarkan Oleh :</p>
                        <p style="font-weight: bold;">{{ $company['name'] }}</p>
                        <p>{{ $company['address'] }}</p>
                        <p>Indonesia</p>
                    </div>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <table class="table-event summary-event">
                        <tr>
                            <td>
                                Detail Acara
                            </td>
                            <td>:</td>
                            <td>{{ $event['title'] }}</td>
                        </tr>
                        <tr>
                            <td>
                                Tanggal
                            </td>
                            <td>:</td>
                            <td>{{ $event['date'] }}</td>
                        </tr>
                        <tr>
                            <td>
                                Venue
                            </td>
                            <td>:</td>
                            <td>{{ $event['venue'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <table class="table-items">
                        <thead>
                            <tr>
                                <th>Deskripsi</th>
                                <th>Harga (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #e6e6e6;">
                                    <div class="item-first">
                                        <p>LED Visual Content</p>
                                        <p>Content Media Size: </p>

                                        @foreach ($ledDetails as $item)
                                           <p>{{ $item['name'] }} : {{ $item['size'] }}</p> 
                                        @endforeach

                                        <p style="font-weight: bold; margin-top: 10px;">Premium LED Visual</p>

                                        <ul style="padding-left: 15px; padding-top: 5px; margin-top: 0;">
                                            @foreach ($items as $detail)
                                                <li>{{ $detail }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </td>
                                <td style="border: 1px solid #e6e6e6;">
                                    <span>{{ $price }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <table class="table-rules">
                        <tbody>
                            <tr>
                                <td>
                                    <div>
                                        <ul style="list-style: circle; padding-left: 10px; padding-top: 0; font-size: 12px; line-height: 1.4">
                                            <li>Minimum Down Payment sebesar 50% dari total biaya yang ditagihkan, biaya tersebut tidak dapat dikembalikan.</li>
                                            <li>Pembayaran melalui rekening BCA 188 060 1225 a/n Wesley Wiyadi / Edwin Chandra Wijaya</li>
                                            <li>Biaya diatas tidak termasuk pajak.</li>
                                            <li>Biaya layanan diatas hanya termasuk perlengkapan multimedia DFACTORY dan tidak termasuk persewaan unit LED dan sistem multimedia lainnya bila diperlukan.</li>
                                            <li>Biaya diatas termasuk Akomodasi untuk Crew bertugas di hari-H event.</li>
                                        </ul>
                                        {{-- {!! $rules !!} --}}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="watermark" style="width: 470px; opacity: .1; position: absolute; top: 50%; left: 55%; transform: translate(-50%, -50%);">
        <img src="{{ public_path() . '/df-logo.png' }}" alt="watermark" style="width: 100%;">
    </div>
    <table class="main-wrapper">
        <tbody>
            <tr class="header">
                <td class="header-left">
                    <div class="header-left-content">
                        <img src="{{ public_path() . '/df-simple-black.png' }}" alt="Logo">
    
                        <div class="divider"></div>
    
                        <div class="address-wrapper">
                            <p>{{ $company['address'] }}</p>
                            <p>{{ $company['phone'] }}</p>
                            <p>{{ $company['email'] }}</p>
                            <table style="font-size: 12px;">
                                <tbody>
                                    <tr>
                                        <td style="padding-top: 10px;">
                                            <img src="{{ public_path() . '/images/instagram.png' }}" alt="instagram" style="width: 15px; height: auto;">
                                        </td>
                                        <td style="padding-top: 10px;">
                                            <span>@dfactory_</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="{{ public_path() . '/images/line.png' }}" alt="line" style="width: 15px; height: auto;">
                                        </td>
                                        <td>
                                            <span>@dfactory</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
    
                        <div class="footer">
                            <img src="{{ public_path() . '/df-logo.png' }}" alt="Logo" style="width: 130px;">
                        </div>
                    </div>
                </td>
                <td class="header-right">
                    <table>
                        <tr class="quotation-number">
                            <td class="quotation-number-title first">Quotation No</td>
                            <td class="quotation-number-separator first">:</td>
                            <td class="quotation-number-value first">{{ $quotationNumber }}</td>
                        </tr>
                        <tr class="quotation-number">
                            <td class="quotation-number-title">Tanggal</td>
                            <td class="quotation-number-separator">:</td>
                            <td class="quotation-number-value">{{ $date }}</td>
                        </tr>
                        <tr class="quotation-number">
                            <td class="quotation-number-title">Design Job</td>
                            <td class="quotation-number-separator">:</td>
                            <td class="quotation-number-value">{{ $designJob }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <div class="conditions" style="font-size: 12px; margin-top: -100px;">
                        <p class="condition-title" style="font-size: 20px; font-weight: bold; margin: 0;">Detail Penawaran (Custom Content)</p>
                        <p style="text-wrap: wrap; width: 80%;"><span style="font-weight: bold; margin: 0;">DFACTORY</span> sebagai penyedia jasa layanan konten digital LED menyiapkan konten dengan tahapan pengerjaan sebagai berikut :</p>
                        <ul style="padding-left: 20px; padding-top: 10px; width: 70%; font-size: 13px; line-height: 1.4; text-align: justify;">
                            <li>
                                <span style="font-weight: bold;">Draft</span> <br>
                                <span>
                                    Tim DFACTORY menyiapkan pilihan draft sketsa konten yang masih dapat di revisi sebanyak 2 ( Dua ) kali revisi yang akan dipilih salah satu diantaranya dan setelah disetujui maka akan dibuat versi digital dari sketsa yang dipilih.
                                </span>
                            </li>
                            <li style="margin-top: 10px;">
                                <span style="font-weight: bold;">Content Making</span> <br>
                                <span>
                                    Tim DFACTORY mengerjakan versi digital dari sketsa yang telah disetujui dan dipilih oleh client, pada saat pengerjaan kami akan memberikan preview secara keseluruhan konten yang telah disiapkan. Pada tahapan ini maka client tidak dapat merevisi konten, hanya dapat melakukan penyesuaian warna dan tatanan.
                                </span>
                            </li>
                            <li style="margin-top: 10px;">
                                <span style="font-weight: bold;">Animating</span> <br>
                                <span>
                                    Tim DFACTORY sesuai dengan persetujuan client mengerjakan konten digital yang telah disetujui dan mengolah menjadi konten animasi.
                                </span>
                            </li>
                            <li style="margin-top: 10px;">
                                <span style="font-weight: bold;">Preview</span> <br>
                                <span>
                                    Tim DFACTORY menyiapkan preview konten apa saja yang telah dikerjakan dan memberikan preview tersebut kepada client. Konten yang di preview akan sama dengan konten yang tampil di lapangan.
                                </span>
                            </li>
                            <li style="margin-top: 10px;">
                                <span style="font-weight: bold;">Event</span> <br>
                                <span>
                                    Pada saat event dilaksanakan, tim DFACTORY akan standby di lapangan untuk memberikan bantuan sesuai dengan layanan yang kami berikan. Tim DFACTORY hanya bertanggung jawab atas konten yang telah dikerjakan oleh tim internal DFACTORY dan setiap konten yang bukan merupakan konten hasil karya DFACTORY berada diluar tanggung jawab DFACTORY sebagai penyedia konten digital LED.
                                </span>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <div class="watermark" style="width: 470px; opacity: .1; position: absolute; top: 50%; left: 55%; transform: translate(-50%, -50%);">
        <img src="{{ public_path() . '/df-logo.png' }}" alt="watermark" style="width: 100%;">
    </div>
    <table class="main-wrapper">
        <tbody>
            <tr class="header">
                <td class="header-left">
                    <div class="header-left-content">
                        <img src="{{ public_path() . '/df-simple-black.png' }}" alt="Logo">
    
                        <div class="divider"></div>
    
                        <div class="address-wrapper">
                            <p>{{ $company['address'] }}</p>
                            <p>{{ $company['phone'] }}</p>
                            <p>{{ $company['email'] }}</p>
                            <table style="font-size: 12px;">
                                <tbody>
                                    <tr>
                                        <td style="padding-top: 10px;">
                                            <img src="{{ public_path() . '/images/instagram.png' }}" alt="instagram" style="width: 15px; height: auto;">
                                        </td>
                                        <td style="padding-top: 10px;">
                                            <span>@dfactory_</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <img src="{{ public_path() . '/images/line.png' }}" alt="line" style="width: 15px; height: auto;">
                                        </td>
                                        <td>
                                            <span>@dfactory</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
    
                        <div class="footer">
                            <img src="{{ public_path() . '/df-logo.png' }}" alt="Logo" style="width: 130px;">
                        </div>
                    </div>
                </td>
                <td class="header-right">
                    <table>
                        <tr class="quotation-number">
                            <td class="quotation-number-title first">Quotation No</td>
                            <td class="quotation-number-separator first">:</td>
                            <td class="quotation-number-value first">{{ $quotationNumber }}</td>
                        </tr>
                        <tr class="quotation-number">
                            <td class="quotation-number-title">Tanggal</td>
                            <td class="quotation-number-separator">:</td>
                            <td class="quotation-number-value">{{ $date }}</td>
                        </tr>
                        <tr class="quotation-number">
                            <td class="quotation-number-title">Design Job</td>
                            <td class="quotation-number-separator">:</td>
                            <td class="quotation-number-value">{{ $designJob }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <div class="conditions" style="font-size: 12px; margin-top: -120px;">
                        <p class="condition-title" style="font-size: 20px; font-weight: bold; margin: 0;">Syarat & Ketentuan Penawaran :</p>
                        <ul style="padding-left: 20px; padding-top: 10px; width: 65%; font-size: 13px; line-height: 1.4; list-style:upper-roman; text-align: justify;">
                            <li>
                                <span style="font-weight: bold;">Permintaan Perubahan Konten</span> <br>
                                <ul style="padding-left: 30px; padding-top: 0; width: 100%; list-style:lower-alpha;">
                                    <li>
                                        Memasuki tahap Content Making, setelah melewati proses Compose, maka tidak diperkenankan melakukan perubahan konten secara menyeluruh, apabila dibutuhkan perubahan secara menyeluruh maka akan dianggap sebagai pembuatan konten baru dengan tambahan biaya, dan apabila waktu produksi masih memungkinkan, akan di produksi sesuai dengan tahapan pengerjaan konten.
                                    </li>
                                    <li>
                                        Pihak DFACTORY berhak menolak permintaan perubahan konten dengan pertimbangan waktu produksi, kinerja tim dan kualitas konten yang akan dihasilkan dari permintaan tersebut.
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <span style="font-weight: bold;">Permintaan Setelah Technical Meeting</span> <br>
                                <ul style="padding-left: 30px; padding-top: 0; width: 100%; list-style:lower-alpha;">
                                    <li>
                                        Setelah technical meeting (TM) tidak diperkenankan menambah atau merubah konten secara signifikan dari draft yang disepakati ataupun konten yang telah di produksi, konfirmasi terakhir konten yang akan dibawa oleh tim DFACTORY paling lambat sebelum Technical Meeting dilaksanakan di tanggal yang telah disetujui.
                                    </li>
                                    <li>
                                        Pihak DFACTORY berhak menolak permintaan perubahan konten dengan pertimbangan waktu produksi, kinerja tim dan kualitas konten yang akan dihasilkan dari permintaan tersebut.
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <span style="font-weight: bold;">Permintaan Meeting diluar kota Surabaya</span> <br>
                                <span>
                                    Untuk pertemuan yang dilakukan secara onsite, akomodasi dan transportasi akan ditanggung oleh pihak klien apabila meeting dilaksanakan pada atau di luar jadwal TM.
                                </span>
                            </li>
                            <li>
                                <span style="font-weight: bold;">Permintaan Penambahan Konten</span> <br>
                                <span>
                                    Segala bentuk penambahan konten diluar dari penawaran awal layanan DFACTORY akan dikenakan biaya tambahan, Pihak DFACTORY berhak menolak permintaan perubahan konten dengan pertimbangan waktu produksi, kinerja tim dan kualitas konten yang akan dihasilkan dari permintaan tersebut.
                                </span>
                            </li>
                        </ul>

                        <p style="margin-top: 8px; width: 75%;">
                            Terima kasih telah mempercayai DFACTORY sebagai partner Stage Visual Effect anda. Besar harapan kami untuk dapat bekerja sama dengan anda. Atas kesempatan yang diberikan kepada kami, kami ucapkan terima kasih.
                        </p>
                    </div>
                </td>
            </tr>

            <tr style="padding-top: 40px;">
                <td></td>
                <td style="text-align: center;">
                    <div style="padding-top: 50px;">
                        <p style="font-size: 12px; margin: 0;">{{ $marketing['name'] }}</p>
                        <p style="font-size: 12px; border-bottom: 1px solid #000; margin: 0; padding-bottom: 5px;">(<i>Marketing</i>)</p>
                        <p style="font-size: 12px; margin: 0;">Dibuat Oleh</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>