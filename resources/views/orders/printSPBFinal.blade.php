<!DOCTYPE html>
<html>

<head>
    <style>
        @page {
            margin: 10px 25px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .header {
            margin-bottom: 10px;
            padding-bottom: 5px;
        }

        .header-text {
            line-height: 1.2;
            font-size: 12px;
        }

        .header-logo {
            width: 90px;
            top: 10px;
            left: 30px;
        }

        .line {
            border-top: 2px solid black;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .info {
            width: 100%;
            margin-top: 10px;
        }

        .info td {
            padding: 5px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            text-decoration: underline;
            margin: 20px 0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.items th,
        table.items td {
            border: 1px solid black;
            padding: 6px;
            height: 20px;
        }

        table.items th {
            text-align: center;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            width: 100%;
        }

        .signature {
            margin-top: 50px;
            text-align: right;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>

    @foreach ($grouped as $type => $items)
        @if (!$loop->first)
            <div style="page-break-before: always;"></div>
        @endif

        @if ($type == 'REGULER')
            @foreach ($items as $creditorCode => $items)
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:120px; vertical-align:top;">
                            <img src="{{ public_path('img/logo-shb.png') }}" style="width:100px;">
                        </td>

                        <td style="vertical-align:top; padding-left:10px;">
                            <h2 style="margin:0;">APOTEK SAHABAT</h2>
                            <div>Jl. Palang Merah Indonesia No. 16 - B Samarinda</div>
                            <div>HP. 0812 5758 6688</div>
                            <div>Apoteker : apt. Nurlina Muliani, S.Farm., M. Farm</div>
                            <div>No. SIPA : 500.16.7/100/SIPA/100.26</div>
                            <div>No. SIA : 12440004111020004</div>
                        </td>
                    </tr>
                </table>

                <div class="line"></div>

                {{-- NO & KEPADA --}}
                <table class="info">
                    <tr>
                        <td style="width:50%;">
                            No : {{ $order->code }}
                        </td>
                        <td style="width:50%; text-align:right;">
                            Kepada Yth : {{ optional($items->first()->creditors)->name }}

                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align:right;">
                            Di- {{ optional($items->first()->creditors)->city }}
                        </td>
                    </tr>
                </table>

                {{-- TITLE --}}
                <div class="title">SURAT PESANAN</div>

                <div>Mohon dikirim obat-obatan untuk keperluan apotek :</div>

                {{-- TABLE --}}
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width:15%;">Jumlah</th>
                            <th style="width:55%;">Nama Obat</th>
                            <th style="width:30%;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $row)
                            <tr>
                                <td style="text-align:center;">
                                    {{ optional($row->receiving_items)->qty_received ?? 0 }}
                                </td>
                                <td>
                                    {{ $row->medicines->name ?? '-' }}
                                </td>
                                <td>
                                    {{-- optional --}}
                                </td>
                            </tr>
                        @endforeach

                        {{-- Fill empty rows so table always looks full --}}
                        @for ($i = count($items); $i < 15; $i++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                {{-- FOOTER --}}
                <table class="footer">
                    <tr>
                        <td style="width:50%;">
                            Asli : Arsip PBF <br>
                            Copy : Arsip Apotek
                        </td>
                        <td style="width:50%; text-align:right;">
                            Samarinda, {{ $date }}
                            <br><br>
                            Penanggung Jawab,
                            <br><br><br><br>
                            <b>apt. Nurlina Muliani, S.Farm., M. Farm</b><br>
                            SIPA : 500.16.7/100/SIPA/100.26
                        </td>
                    </tr>
                </table>
                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        @elseif ($type == 'PREKURSOR')
            <style>
                .center {
                    text-align: center;
                }

                .underline {
                    text-decoration: underline;
                    font-weight: bold;
                    font-size: 20px;
                }

                .section {
                    margin-top: 25px;
                }

                table.form-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }

                table.form-table th,
                table.form-table td {
                    border: 1px solid black;
                    padding: 6px;
                    text-align: center;
                }

                table.form-table th {
                    font-weight: bold;
                }

                .text-left {
                    text-align: left;
                }

                .signature {
                    margin-top: 70px;
                    text-align: center;
                }

                .notes {
                    font-size: 14px;
                    margin-top: 40px;
                }
            </style>
            @foreach ($items as $creditorCode => $items)
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:120px; vertical-align:top;">
                            <img src="{{ public_path('img/logo-shb.png') }}" style="width:100px;">
                        </td>

                        <td style="vertical-align:top; padding-left:10px;">
                            <h2 style="margin:0;">APOTEK SAHABAT</h2>
                            <div>Jl. Palang Merah Indonesia No. 16 - B Samarinda</div>
                            <div>HP. 0812 5758 6688</div>
                            <div>Apoteker : apt. Nurlina Muliani, S.Farm., M. Farm</div>
                            <div>No. SIPA : 500.16.7/100/SIPA/100.26</div>
                            <div>No. SIA : 12440004111020004</div>
                        </td>
                    </tr>
                </table>

                <div class="line"></div>

                {{-- NO & KEPADA --}}
                <table class="info">
                    <tr>
                        <td style="width:50%;">
                            No : {{ $order->code }}
                        </td>
                        <td style="width:50%; text-align:right;">
                            Kepada Yth : {{ optional($items->first()->creditors)->name }}

                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align:right;">
                            Di- {{ optional($items->first()->creditors)->city }}
                        </td>
                    </tr>
                </table>

                {{-- TITLE --}}
                <div class="title">SURAT PESANAN</div>

                <div>Mohon dikirim obat-obatan untuk keperluan apotek :</div>

                {{-- TABLE --}}
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width:15%;">Jumlah</th>
                            <th style="width:55%;">Nama Obat</th>
                            <th style="width:30%;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $row)
                            <tr>
                                <td style="text-align:center;">
                                    {{ optional($row->receiving_items)->qty_received ?? 0 }}
                                </td>
                                <td>
                                    {{ $row->medicines->name ?? '-' }}
                                </td>
                                <td>
                                    {{-- optional --}}
                                </td>
                            </tr>
                        @endforeach

                        {{-- Fill empty rows so table always looks full --}}
                        @for ($i = count($items); $i < 15; $i++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                {{-- FOOTER --}}
                <table class="footer">
                    <tr>
                        <td style="width:50%;">
                            Asli : Arsip PBF <br>
                            Copy : Arsip Apotek
                        </td>
                        <td style="width:50%; text-align:right;">
                            Samarinda, {{ $date }}
                            <br><br>
                            Penanggung Jawab,
                            <br><br><br><br>
                            <b>apt. Nurlina Muliani, S.Farm., M. Farm</b><br>
                            SIPA : 500.16.7/100/SIPA/100.26
                        </td>
                    </tr>
                </table>
                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        @elseif ($type == 'Obat Tertentu')
            @foreach ($items as $creditorCode => $items)
                <div style="text-align:center; font-weight:bold; font-size:18px; text-decoration:underline;">
                    SURAT PESANAN OBAT-OBAT TERTENTU
                </div>

                <div style="text-align:center; margin-top:5px;">
                    Nomor : {{ $order->code }}
                </div>

                <br><br>

                <div>Yang bertanda tangan dibawah ini :</div>
                <br>

                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:100px;">Nama</td>
                        <td style="width:10px;">:</td>
                        <td style="border-bottom:1px dotted #000;">
                            apt. Nurlina Muliani, S.Farm., M.Farm
                        </td>
                    </tr>
                    <tr>
                        <td>Jabatan</td>
                        <td>:</td>
                        <td style="border-bottom:1px dotted #000;">
                            Apoteker Pengelola Apotek
                        </td>
                    </tr>
                </table>

                <br>

                <div>
                    Mengajukan pesanan Obat-Obat Tertentu kepada :
                </div>

                <br>

                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:150px;">Nama Distributor</td>
                        <td style="width:10px;">:</td>
                        <td style="border-bottom:1px dotted #000;">
                            {{ optional($items->first()->creditors)->name ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td style="border-bottom:1px dotted #000;">
                            {{ optional($items->first()->creditors)->address ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td>Telp.</td>
                        <td>:</td>
                        <td style="border-bottom:1px dotted #000;">
                            {{ optional($items->first()->creditors)->phone ?? '-' }}
                        </td>
                    </tr>
                </table>

                <br><br>

                <div>
                    Dengan Obat-Obat Tertentu yang dipesan adalah :
                </div>

                <br>

                <table style="width:100%; border-collapse:collapse;">
                    @php $no = 1; @endphp
                    @foreach ($items as $row)
                        <tr>
                            <td style="width:30px;">{{ $no++ }})</td>
                            <td style="border-bottom:1px dotted #000;">
                                {{ $row->medicines->name ?? '-' }}
                                ({{ optional($row->receiving_items)->qty_received ?? 0 }})
                            </td>
                        </tr>
                    @endforeach

                    {{-- Fill until 4 rows like official form --}}
                    @for ($i = count($items); $i < 4; $i++)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td style="border-bottom:1px dotted #000;">&nbsp;</td>
                        </tr>
                    @endfor
                </table>

                <br><br>

                <div>
                    Obat-Obat Tertentu tersebut akan dipergunakan untuk :
                </div>

                <br>

                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:150px;">Nama Sarana</td>
                        <td style="width:10px;">:</td>
                        <td>
                            Apotek Sahabat
                            <br>
                            ( PBF / Apotek / Instalasi Farmasi Rumah Sakit /
                            Instalasi Farmasi Klinik )
                        </td>
                    </tr>
                    <tr>
                        <td>Alamat Sarana</td>
                        <td>:</td>
                        <td>
                            Jl. Palang Merah Indonesia No. 16 - B Samarinda
                        </td>
                    </tr>
                </table>

                <br><br><br>

                <div style="text-align:right;">
                    Samarinda, {{ $date }}
                    <br><br>
                    Pemesan
                    <br><br><br><br>
                    ( apt. Nurlina Muliani, S.Farm., M.Farm )
                </div>

                <br><br>

                <div style="font-size:12px;">
                    Catatan : <br>
                    - Satu Surat pesanan hanya berlaku untuk satu jenis Obat-Obat tertentu. <br>
                    - Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.
                </div>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        @elseif ($type == 'NARKOTIKA')
            <style>
                .center {
                    text-align: center;
                }

                .underline {
                    text-decoration: underline;
                    font-weight: bold;
                    font-size: 20px;
                }

                .section {
                    margin-top: 25px;
                }

                table.form-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }

                table.form-table th,
                table.form-table td {
                    border: 1px solid black;
                    padding: 6px;
                    text-align: center;
                }

                table.form-table th {
                    font-weight: bold;
                }

                .text-left {
                    text-align: left;
                }

                .signature {
                    margin-top: 70px;
                    text-align: center;
                }

                .notes {
                    font-size: 14px;
                    margin-top: 40px;
                }

                .total-wrapper {
                    display: flex;
                    border: 1px solid #000;
                    height: 40px;
                    align-items: center;
                    font-size: 14px;
                }

                .total-empty {
                    flex: 1;
                }

                .total-label {
                    width: 120px;
                    border-left: 1px solid #000;
                    border-right: 1px solid #000;
                    text-align: center;
                    font-weight: bold;
                }

                .total-value {
                    width: 120px;
                    text-align: right;
                    padding-right: 10px;
                }

                .form-table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .form-table th,
                .form-table td {
                    border: 1px solid #000;
                    padding: 6px;
                    font-size: 9px;
                }

                .total-row td:first-child {
                    border-left: none;
                }

                .total-row td[colspan] {
                    border-top: none;
                }
            </style>
            @foreach ($items as $creditorCode => $items)
                <div class="center">
                    <div class="underline">SURAT PESANAN NARKOTIKA</div>
                    Nomor : {{ $order->code }}
                </div>

                {{-- IDENTITAS --}}
                <div class="section">
                    Yang bertanda tangan dibawah ini : <br><br>

                    Nama : apt. Nurlina Muliani, S.Farm., M. Farm <br>
                    Jabatan : Apoteker Pengelola Apotek <br><br>

                    Mengajukan pesanan Narkotika kepada :
                    <br><br>

                    Nama Distributor : {{ optional($items->first()->creditors)->name ?? '-' }} <br>
                    Alamat : {{ optional($items->first()->creditors)->address ?? '-' }} <br>
                    Telp. : {{ optional($items->first()->creditors)->phone ?? '-' }}
                </div>

                {{-- TABLE TITLE --}}
                <div class="section">
                    Dengan Narkotika yang dipesan adalah :
                </div>

                {{-- TABLE --}}
                <table class="form-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">No.</th>
                            <th style="width:35%;">NAMA OBAT</th>
                            <th style="width:20%;">BENTUK SEDIAAN</th>
                            <th style="width:20%;">KEKUATAN SEDIAAN</th>
                            <th style="width:20%;">JUMLAH</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="text-left">{{ $row->medicines->name ?? '-' }}</td>
                                <td>{{ $row->medicines->form ?? '-' }}</td>
                                <td>{{ $row->medicines->strength ?? '-' }}</td>
                                <td>{{ optional($row->receiving_items)->qty_received ?? 0 }}</td>
                            </tr>
                        @endforeach

                        {{-- Empty rows --}}
                        @for ($i = count($items); $i < 8; $i++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>

                    @php
                        $totalprice = $items->sum('total');
                    @endphp

                    <tfoot>
                        <tr class="total-row">
                            <!-- empty space (first 3 columns merged) -->
                            <td colspan="3" style="border:none;"></td>

                            <!-- Total label -->
                            <td style="text-align:center; font-weight:bold;">
                                Total
                            </td>

                            <!-- Total value -->
                            <td style="font-weight:bold; text-align:center;">
                                Rp. {{ number_format($totalprice) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                {{-- SARANA --}}
                <div class="section">
                    Narkotika tersebut akan dipergunakan untuk :
                    <br><br>

                    Nama Sarana : Apotek Sahabat <br>
                    Alamat Sarana : Jl. Palang Merah Indonesia No. 16 - B Samarinda
                </div>

                {{-- SIGNATURE --}}
                <div class="section center">
                    Samarinda, {{ $date }}
                    <br><br>
                    Pemesan
                </div>

                <div class="signature">
                    <span style="text-decoration: underline;">
                        apt. Nurlina Muliani, S.Farm., M. Farm
                    </span>
                    <br>
                    SIPA : 500.16.7/100/SIPA/100.26
                </div>

                {{-- NOTES --}}
                <div class="notes">
                    Catatan : <br>
                    - Satu Surat pesanan hanya berlaku untuk satu jenis Narkotika. <br>
                    - Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.
                </div>
                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        @elseif ($type == 'Psikotropika')
            <style>
                .center {
                    text-align: center;
                }

                .underline {
                    text-decoration: underline;
                    font-weight: bold;
                    font-size: 20px;
                }

                .section {
                    margin-top: 25px;
                }

                table.form-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }

                table.form-table th,
                table.form-table td {
                    border: 1px solid black;
                    padding: 6px;
                    text-align: center;
                }

                table.form-table th {
                    font-weight: bold;
                }

                .text-left {
                    text-align: left;
                }

                .signature {
                    margin-top: 70px;
                    text-align: center;
                }

                .notes {
                    font-size: 14px;
                    margin-top: 40px;
                }
            </style>
            @foreach ($items as $creditorCode => $items)
                <div class="center">
                    <div class="underline">SURAT PESANAN PSIKOTROPIKA</div>
                    Nomor : {{ $order->code }}
                </div>

                {{-- IDENTITAS --}}
                <div class="section">
                    Yang bertanda tangan dibawah ini : <br><br>

                    Nama : apt. Nurlina Muliani, S.Farm., M. Farm <br>
                    Jabatan : Apoteker Pengelola Apotek <br><br>

                    Mengajukan pesanan Psikotropika kepada :
                    <br><br>

                    Nama Distributor : {{ optional($items->first()->creditors)->name ?? '-' }} <br>
                    Alamat : {{ optional($items->first()->creditors)->address ?? '-' }} <br>
                    Telp. : {{ optional($items->first()->creditors)->phone ?? '-' }}
                </div>

                {{-- TABLE TITLE --}}
                <div class="section">
                    Dengan Psikotropika yang dipesan adalah :
                </div>

                {{-- TABLE --}}
                <table class="form-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">No.</th>
                            <th style="width:35%;">NAMA OBAT</th>
                            <th style="width:20%;">BENTUK SEDIAAN</th>
                            <th style="width:20%;">KEKUATAN SEDIAAN</th>
                            <th style="width:20%;">JUMLAH</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="text-left">{{ $row->medicines->name ?? '-' }}</td>
                                <td>{{ $row->medicines->form ?? '-' }}</td>
                                <td>{{ $row->medicines->strength ?? '-' }}</td>
                                <td>{{ optional($row->receiving_items)->qty_received ?? 0 }}</td>
                            </tr>
                        @endforeach

                        {{-- Fill empty rows to look like official form --}}
                        @for ($i = count($items); $i < 8; $i++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                {{-- SARANA --}}
                <div class="section">
                    Psikotropika tersebut akan dipergunakan untuk :
                    <br><br>

                    Nama Sarana : Apotek Sahabat <br>
                    Alamat Sarana : Jl. Palang Merah Indonesia No. 16 - B Samarinda
                </div>

                {{-- SIGNATURE --}}
                <div class="section center">
                    Samarinda, {{ $date }}
                    <br><br>
                    Pemesan
                </div>

                <div class="signature">
                    <span style="text-decoration: underline;">
                        apt. Nurlina Muliani, S.Farm., M. Farm
                    </span>
                    <br>
                    SIPA : 500.16.7/100/SIPA/100.26
                </div>

                {{-- NOTES --}}
                <div class="notes">
                    Catatan : <br>
                    - Satu Surat pesanan hanya berlaku untuk satu jenis Narkotika. <br>
                    - Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.
                </div>
                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        @endif
    @endforeach

</body>

</html>
