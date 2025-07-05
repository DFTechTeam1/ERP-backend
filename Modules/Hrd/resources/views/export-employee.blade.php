<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>

    <style>
        /* body {
            font-family: Arial, sans-serif;
            background-color: black;
            color: yellow;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        table {
            border-collapse: collapse;
            border: 2px solid yellow;
        }
        th, td {
            border: 1px solid yellow;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: black;
            color: yellow;
        }
        td {
            background-color: black;
            color: yellow;
        } */
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>Employee ID *</th>
                <th>Barcode</th>
                <th>First Name *</th>
                <th>Last Name</th>
                <th>Email *</th>
                <th>NIK (NPWP 16 digit)</th>
                <th>Citizen ID Address</th>
                <th>Residential Address</th>
                <th>Place of Birth</th>
                <th>Date of Birth *</th>
                <th>Mobile Phone Number</th>
                <th>Home Phone Number</th>
                <th>Gender *</th>
                <th>Marital Status *</th>
                <th>Religion *</th>
                <th>Organization Name *</th>
                <th>Job Position *</th>
                <th>Job Level *</th>
                <th>Grade</th>
                <th>Class</th>
                <th>Employment Status *</th>
                <th>Join Date *</th>
                <th>End Employment Status Date</th>
                <th>Sign Date</th>
                <th>NPWP</th>
                <th>Taxable Date</th>
                <th>PTKP Status *</th>
                <th>Bank Name</th>
                <th>Bank Account</th>
                <th>Bank Account Holder</th>
                <th>BPJS Ketenagakerjaan</th>
                <th>BPJS Kesehatan</th>
                <th>Resign Date</th>
                <th>Branch Name</th>
                <th>Type Salary</th>
                <th>Overtime Status</th>
                <th>Passport</th>
                <th>Passport Expired Date</th>
                <th>Blood Type</th>
                <th>Postal Code</th>
                <th>BPJS Kesehatan Family</th>
                <th>Employee Tax Status*</th>
                <th>JHT Config</th>
                <th>Tax Config</th>
                <th>BPJS Kesehatan Config</th>
                <th>Jaminan Pensiun Config</th>
                <th>NPP BPJS Ketenagakerjaan</th>
                <th>Beginning Netto</th>
                <th>PPH 21 Paid</th>
                <th>Ekspatriat DN Date</th>
                <th>Nationality Code</th>
                <th>BPJS Ketenagakerjaan Date</th>
                <th>BPJS Kesehatan Date</th>
                <th>Jaminan Pensiun Date</th>
                <th>Payment Schedule</th>
                <th>Salary Config</th>
                <th>Currency</th>
                <th>Cost Center</th>
                <th>Cost Center Category</th>
                <th>Work Schedule</th>
                <th>Overtime Working Day Default</th>
                <th>Overtime Day Off Default</th>
                <th>Overtime National Holiday Default</th>
                <th>Split Payment Policy</th>
                <th>Bank Name Secondary</th>
                <th>Bank Account Secondary</th>
                <th>Bank Account Holder Secondary</th>
                <th>Bank Name Tertiary</th>
                <th>Bank Account Tertiary</th>
                <th>Bank Account Holder Tertiary</th>
                <th>Jenis Dok. Referensi Bukti Potong</th>
                <th>Nomor Dok. Referensi Bukti Potong</th>
                <th>Tanggal Dok. Referensi Bukti Potong</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($employees as $key => $employee)
                @if ($employee->jobLevel)
                <tr>
                    <td class="employee-id">{{ $employee->employee_id }}</td>
                    <td class="barcode">{{ $key + 1 }}</td>
                    <td class="first-name">{{ $employee->first_name }}</td>
                    <td class="last-name">{{ $employee->last_name }}</td>
                    <td class="email">{{ $employee->email }}</td>
                    <td class="nik">{{ $employee->id_number }}</td>
                    <td class="citizen-id-address">{{ $employee->address }}</td>
                    <td class="residential-address">{{ $employee->current_address }}</td>
                    <td class="place-of-birth">{{ $employee->place_of_birth }}</td>
                    <td class="date-of-birth">{{ $employee->date_of_birth }}</td>
                    <td class="mobile-phone-number">{{ $employee->phone }}</td>
                    <td class="home-phone-number"></td>
                    <td class="gender">{{ $employee->gender === 'male' ? 1 : 2 }}</td>
                    <td class="martial-status">{{ $employee->martial_status == 'single' ? 1 : 2 }}</td>
                    <td class="religion">{{ $employee->religion_code }}</td>
                    <td class="organization-name">{{ $employee->position->division->name }}</td>
                    <td class="job-position">{{ $employee->position->name }}</td>
                    <td class="job-level">{{ $employee->jobLevel ? $employee->jobLevel->name : '-' }}</td>
                    <td class="grade"></td>
                    <td class="class"></td>
                    <td class="employement-status">Permanent</td>
                    <td class="join-date">{{ $employee->join_date }}</td>
                    <td class="end-employement-status-date"></td>
                    <td class="sign-date"></td>
                    <td class="npwp"></td>
                    <td class="taxable-date"></td>
                    <td class="ptkp-status">1</td>
                    <td class="bank-name">{{ $employee->bank_detail[0]['bank_id'] }}</td>
                    <td class="bank-account"> {{ $employee->bank_detail[0]['account_number'] }}</td>
                    <td class="bank-account-holder">{{ $employee->bank_detail[0]['account_holder_name'] }}</td>
                    <td class="bpjs-ketenagakerjaan"></td>
                    <td class="bpjs-kesehatan"></td>
                    <td class="resign-date"></td>
                    <td class="branch-name">{{ $employee->branch ? $employee->branch->name : '-' }}</td>
                    <td class="type-salary">1</td>
                    <td class="overtime-status"></td>
                    <td class="passport"></td>
                    <td class="passport-expired-date"></td>
                    <td class="blood-type">1</td>
                    <td class="postal-code"></td>
                    <td class="bpjs-kesehatan-family"></td>
                    <td class="employee-tax-status">0</td>
                    <td class="jht-config">0</td>
                    <td class="tax-config">1</td>
                    <td class="bpjs-kesehatan-config"></td>
                    <td class="jaminan-pensiun-config"></td>
                    <td class="npp-bpjs-kesehatan"></td>
                    <td class="beginning-netto"></td>
                    <td class="pph-21-paid"></td>
                    <td class="ekspatriat-dn-date"></td>
                    <td class="nationality-code"></td>
                    <td class="bpjs-ketenagakerjaan-date"></td>
                    <td class="bpjs-kesehatan-date"></td>
                    <td class="jaminan-pensiun-date"></td>
                    <td class="payment-schedule"></td>
                    <td class="salary-config">2</td>
                    <td class="currency"></td>
                    <td class="cost-center"></td>
                    <td class="const-center-category"></td>
                    <td class="work-schedule"></td>
                    <td class="overtime-working-day-default"></td>
                    <td class="overtime-day-off-default"></td>
                    <td class="overtime-national-holiday-default"></td>
                    <td class="split-payment-policy"></td>
                    <td class="bank-name-secondary"></td>
                    <td class="bank-account-secondary"></td>
                    <td class="bank-account-holder-secondary"></td>
                    <td class="bank-name-tertiary"></td>
                    <td class="bank-account-holder-tertiary"></td>
                    <td class="jenis-dok-referensi"></td>
                    <td class="nomor-dok-referensi"></td>
                    <td class="tanggal-dok-referensi"></td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
