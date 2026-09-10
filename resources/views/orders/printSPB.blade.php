<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Surat Pesanan</title>
    <style>
        @page {
            margin: 0px 20px 10px 20px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #000;
        }

        table {
            border-collapse: collapse;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .underline {
            text-decoration: underline;
        }

        /* Garis Pemisah Kop */
        .line {
            border-top: 1.5px solid #000;
            margin-top: 5px;
            margin-bottom: 6px;
        }

        /* Styling Tabel Utama */
        table.form-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        table.form-table th,
        table.form-table td {
            border: 0.8px solid #000;
            padding: 5.5px 4px;
            font-size: 9.5px;
            line-height: 1.25;
        }

        table.form-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 10.5px;
            text-align: center;
            padding: 6px 4px;
        }

        .table-fixed-wrap table.form-table th,
        .table-fixed-wrap table.form-table td {
            padding: 3px 3px;
            font-size: 8.5px;
            line-height: 1.2;
        }

        .table-fixed-wrap table.form-table th {
            font-size: 9.5px;
            padding: 4px 3px;
        }

        /* Identitas / Form List */
        .id-table td {
            padding: 1.5px 0;
            vertical-align: top;
            font-size: 9px;
        }

        .id-table td.dotted {
            border-bottom: 1px dotted #000;
            padding: 0 0 1px 0;
        }

        /* Catatan Kaki */
        .notes {
            font-size: 8.5px;
            margin-top: 4px;
            padding-top: 2px;
        }

        /* Mencegah TTD terpotong ke halaman baru */
        .signature-block {
            margin-top: 6px;
            page-break-inside: avoid;
            font-size: 9px;
        }

        .signature-block td {
            font-size: 9px;
            line-height: 1.25;
        }

        .section-gap {
            margin-top: 3px;
            margin-bottom: 2px;
            font-size: 9px;
        }

        .title-main {
            text-align: center;
            font-weight: bold;
            font-size: 13.5px;
            text-decoration: underline;
            margin-top: 4px;
            margin-bottom: 2px;
            letter-spacing: 1px;
        }

        .subtitle {
            text-align: center;
            font-size: 9.5px;
            margin-top: 1px;
        }

        /* Satu chunk non-REGULER adalah satu formulir lengkap. */
        .non-regular-page {
            page-break-inside: avoid;
            break-inside: avoid-page;
        }

        .table-fixed-wrap {
            margin-top: 3px;
            margin-bottom: 3px;
        }

        .table-fixed-wrap table.form-table {
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .table-fixed-wrap table.form-table tbody td {
            height: 30px;
            padding: 2px 3px;
            vertical-align: middle;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .table-fixed-wrap table.form-table tr {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    @php
        $isFirstPage = true;
    @endphp

    @foreach ($grouped as $type => $items)
        {{-- =========================================================
        1) REGULER
        ========================================================== --}}
        @if ($type == 'REGULER')
            @foreach ($items as $creditorCode => $creditorItems)
                @foreach ($creditorItems->chunk(17) as $chunkIndex => $chunkItems)
                    @if (!$isFirstPage)
                        <div style="page-break-before: always;"></div>
                    @endif
                    @php $isFirstPage = false; @endphp

                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:72px; vertical-align:middle;">
                                @if (!empty($logoBase64))
                                    <img src="{{ $logoBase64 }}" style="width:66px; margin-right:6px;">
                                @elseif ($pharmacy->logo && file_exists(public_path('img/' . $pharmacy->logo)))
                                    <img src="{{ public_path('img/' . $pharmacy->logo) }}"
                                        style="width:66px; margin-right:6px;">
                                @else
                                    <img src="{{ public_path('img/logo-sahabat.png') }}" style="width:44px;">
                                @endif
                            </td>
                            <td style="vertical-align:top; padding-left:6px;">
                                <div
                                    style="font-size:14.5px; font-weight:bold; margin-bottom:3px; letter-spacing:0.4px;">
                                    {{ strtoupper($pharmacy->name) }}</div>
                                <div style="font-size:9.8px; line-height:1.28;">
                                    <div>{{ $pharmacy->address }}</div>
                                    <div>HP. {{ $pharmacy->phone }}</div>
                                    <div>Apoteker : {{ $pharmacy->pharmacist }}</div>
                                    <div>No. SIPA : {{ $pharmacy->pharmacist_permit }}</div>
                                    @if ($pharmacy->permit)
                                        <div>No. SIA : {{ $pharmacy->permit }}</div>
                                    @elseif ($pharmacy->pharmacy_registration)
                                        <div>No. STR : {{ $pharmacy->pharmacy_registration }}</div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class="line"></div>

                    {{-- NO & KEPADA --}}
                    <table style="width:100%; margin-top:4px; font-size:10.5px;">
                        <tr>
                            <td style="width:46%; vertical-align:top;">
                                <b>No :</b>
                                {{ $creditorItems->first()->order_items_code }}{{ $chunkIndex > 0 ? ' (Hal ' . ($chunkIndex + 1) . ')' : '' }}
                            </td>
                            <td style="width:54%; text-align:right; vertical-align:top;">
                                <b>Kepada Yth :</b> {{ optional($creditorItems->first()->creditors)->name ?? '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align:right; font-size:9.8px; vertical-align:top;">
                                <div style="height:38px; max-height:38px; overflow:hidden; line-height:12.5px;">
                                    Di- {{ optional($creditorItems->first()->creditors)->address ?? '-' }}
                                </div>
                            </td>
                        </tr>
                    </table>

                    {{-- TITLE --}}
                    <div class="title-main">
                        SURAT PESANAN
                    </div>

                    <div class="section-gap">Mohon dikirim obat-obatan untuk keperluan apotek :</div>

                    {{-- TABLE --}}
                    <table class="form-table">
                        <thead>
                            <tr>
                                <th style="width:7%;">No.</th>
                                <th style="width:47%;">Nama Obat</th>
                                <th style="width:18%;">Jumlah</th>
                                <th style="width:14%;">Satuan</th>
                                <th style="width:14%;">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($chunkItems as $index => $row)
                                <tr>
                                    <td style="text-align:center;">
                                        {{ $chunkIndex * 17 + $index + 1 }}
                                    </td>
                                    <td>
                                        {{ $row->medicines->name ?? '-' }}
                                    </td>
                                    <td style="text-align:center;">
                                        {{ $row->quantity }} ({{ ucfirst(terbilang($row->quantity)) }})
                                    </td>
                                    <td style="text-align:center;">
                                        {{ $row->medicines->packaging ?? '-' }}
                                    </td>
                                    <td style="text-align:center;">
                                        @php
                                            $credCode = $row->creditor_code ?? optional($row->creditors)->code;
                                            $medCred =
                                                $row->medicines->creditors->firstWhere('code', $credCode) ??
                                                $row->medicines->creditors->first();
                                            $disc = $medCred?->pivot?->discount;
                                        @endphp
                                        {{ $disc ? ($disc == (int) $disc ? (int) $disc : $disc) . '%' : '-' }}
                                    </td>
                                </tr>
                            @endforeach

                            @for ($i = count($chunkItems); $i < 17; $i++)
                                <tr>
                                    <td style="text-align:center;">&nbsp;</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>

                    {{-- FOOTER --}}
                    <table class="signature-block" style="width:100%;">
                        <tr>
                            <td style="width:38%; vertical-align:top; color:#333;">
                                Asli : Arsip PBF <br>
                                Copy : Arsip Apotek
                            </td>
                            <td style="width:62%; text-align:right; vertical-align:top;">
                                {{ $pharmacy->city }}, {{ $date }}
                                <br>
                                Penanggung Jawab,

                                @if (!empty($signatureBase64))
                                    <div style="margin: 2px 0;">
                                        <img src="{{ $signatureBase64 }}" style="height:42px; width:auto;">
                                    </div>
                                @elseif ($pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature)))
                                    <div style="margin: 2px 0;">
                                        <img src="{{ public_path('img/' . $pharmacy->signature) }}"
                                            style="height:42px; width:auto;">
                                    </div>
                                @else
                                    <div style="height:42px;"></div> {{-- blank space to sign by hand --}}
                                @endif
                                <b><u>{{ $pharmacy->pharmacist }}</u></b><br>
                                SIPA : {{ $pharmacy->pharmacist_permit }}
                            </td>
                        </tr>
                    </table>
                @endforeach
            @endforeach

            {{-- =========================================================
            2) PREKURSOR
            ========================================================== --}}
        @elseif ($type == 'PREKURSOR')
            @foreach ($items as $creditorCode => $creditorItems)
                @php
                    $spCode = $creditorItems->first()->order_items_code;
                    $itemsPerPage = 11;
                    $chunks = $creditorItems->chunk($itemsPerPage);
                    $totalChunks = $chunks->count();
                @endphp
                @foreach ($chunks as $chunkIndex => $chunkItems)
                    @if (!$isFirstPage)
                        <div style="page-break-before: always;"></div>
                    @endif
                    @php $isFirstPage = false; @endphp

                    <div class="non-regular-page">

                        <div class="title-main">
                            SURAT PESANAN OBAT MENGANDUNG PREKURSOR FARMASI
                        </div>
                        <div class="subtitle">
                            Nomor SP :
                            {{ $spCode }}{{ $totalChunks > 1 ? ' (Hal ' . ($chunkIndex + 1) . ')' : '' }}
                        </div>

                        <div class="section-gap">Yang bertanda tangan dibawah ini :</div>

                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:75px;">Nama Apoteker</td>
                                <td style="width:8px;">:</td>
                                <td><b>{{ $pharmacy->pharmacist }}</b></td>
                            </tr>
                            <tr>
                                <td>Jabatan</td>
                                <td>:</td>
                                <td>Apoteker Pengelola Apotek</td>
                            </tr>
                            <tr>
                                <td>No. SIPA</td>
                                <td>:</td>
                                <td>{{ $pharmacy->pharmacist_permit }}</td>
                            </tr>
                        </table>

                        <div class="section-gap">
                            Mengajukan pesanan obat mengandung Prekursor Farmasi kepada :
                        </div>

                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:75px;">Nama PBF</td>
                                <td style="width:8px;">:</td>
                                <td class="dotted">
                                    <b>{{ optional($chunkItems->first()->creditors)->name ?? '-' }}</b>
                                </td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>:</td>
                                <td class="dotted">
                                    {{ optional($chunkItems->first()->creditors)->address ?? '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td>No. Telp.</td>
                                <td>:</td>
                                <td class="dotted">
                                    {{ optional($chunkItems->first()->creditors)->phone ?? '-' }}
                                </td>
                            </tr>
                        </table>

                        <div class="section-gap">
                            Obat mengandung Prekursor Farmasi tersebut akan digunakan untuk memenuhi kebutuhan :
                        </div>

                        {{-- TABLE WRAPPER FIXED HEIGHT --}}
                        <div class="table-fixed-wrap">
                            <table class="form-table">
                                <thead>
                                    <tr>
                                        <th style="width:5%;">No.</th>
                                        <th style="width:28%;">Nama Obat Mengandung Prekursor Farmasi</th>
                                        <th style="width:36%;">Zat Aktif Prekursor Farmasi</th>
                                        <th style="width:10%;">Satuan</th>
                                        <th style="width:12%;">Jumlah</th>
                                        <th style="width:9%;">Ket.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($chunkItems as $index => $row)
                                        <tr>
                                            <td style="text-align:center;">{{ $chunkIndex * $itemsPerPage + $loop->iteration }}
                                            </td>
                                            <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                            <td style="text-align:center; font-size:8px; line-height:1.15;">
                                                {{ $row->medicines->component ?? '-' }}
                                            </td>
                                            <td style="text-align:center;">{{ $row->medicines->packaging ?? '-' }}</td>
                                            <td style="text-align:center;">{{ $row->quantity }}
                                                ({{ ucfirst(terbilang($row->quantity)) }})
                                            </td>
                                            <td></td>
                                        </tr>
                                    @endforeach

                                    @for ($i = count($chunkItems); $i < 11; $i++)
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>

                        <div class="section-gap">
                            Obat tersebut mengandung Prekursor tersebut akan digunakan untuk memenuhi kebutuhan:
                        </div>

                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:75px;">Nama Apotek</td>
                                <td style="width:8px;">:</td>
                                <td><b>{{ $pharmacy->name }}</b></td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>:</td>
                                <td>{{ $pharmacy->address }}</td>
                            </tr>
                            <tr>
                                <td>
                                    @if ($pharmacy->permit)
                                        No. SIA
                                    @elseif ($pharmacy->pharmacy_registration)
                                        STR
                                    @endif
                                </td>
                                <td>:</td>
                                <td>
                                    {{ $pharmacy->permit ?? $pharmacy->pharmacy_registration }}
                                </td>
                            </tr>
                        </table>

                        {{-- SIGNATURE --}}
                        <table class="signature-block" style="width:100%;">
                            <tr>
                                <td style="width:42%;"></td>
                                <td style="width:58%; text-align:center;">
                                    {{ $pharmacy->city }}, {{ $date }}
                                    <br>
                                    Pemesan,

                                    @if (!empty($signatureBase64))
                                        <div style="margin: 1px 0;">
                                            <img src="{{ $signatureBase64 }}" style="height:35px; width:auto;">
                                        </div>
                                    @elseif ($pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature)))
                                        <div style="margin: 1px 0;">
                                            <img src="{{ public_path('img/' . $pharmacy->signature) }}"
                                                style="height:35px; width:auto;">
                                        </div>
                                    @else
                                        <div style="height:35px;"></div> {{-- blank space to sign by hand --}}
                                    @endif

                                    <b><u>{{ $pharmacy->pharmacist }}</u></b><br>
                                    SIPA : {{ $pharmacy->pharmacist_permit }}
                                </td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            @endforeach

            {{-- =========================================================
            3) OBAT-OBAT TERTENTU (OOT)
            ========================================================== --}}
        @elseif ($type == 'Obat Tertentu' || $type == 'OBAT-OBAT TERTENTU (OOT)')
            @foreach ($items as $creditorCode => $creditorItems)
                @php
                    $spCode = $creditorItems->first()->order_items_code;
                    $itemsPerPage = 11;
                    $chunks = $creditorItems->chunk($itemsPerPage);
                    $totalChunks = $chunks->count();
                @endphp
                @foreach ($chunks as $chunkIndex => $chunkItems)
                    @if (!$isFirstPage)
                        <div style="page-break-before: always;"></div>
                    @endif
                    @php $isFirstPage = false; @endphp

                    <div class="non-regular-page">

                        <div class="title-main">
                            SURAT PESANAN OBAT-OBAT TERTENTU
                        </div>

                        <div class="subtitle">
                            Nomor :
                            {{ $spCode }}{{ $totalChunks > 1 ? ' (Hal ' . ($chunkIndex + 1) . ')' : '' }}
                        </div>

                        <div class="section-gap">Yang bertanda tangan dibawah ini :</div>

                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:75px;">N a m a</td>
                                <td style="width:8px;">:</td>
                                <td>
                                    <b>{{ $pharmacy->pharmacist }}</b>
                                </td>
                            </tr>
                            <tr>
                                <td>Jabatan</td>
                                <td>:</td>
                                <td>
                                    Apoteker Pengelola Apotek
                                </td>
                            </tr>
                        </table>

                        <div class="section-gap">Mengajukan pesanan Obat-Obat Tertentu kepada :</div>

                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:90px;">Nama Distributor</td>
                                <td style="width:8px;">:</td>
                                <td class="dotted">
                                    <b>{{ optional($chunkItems->first()->creditors)->name ?? '-' }}</b>
                                </td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>:</td>
                                <td class="dotted">
                                    {{ optional($chunkItems->first()->creditors)->address ?? '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td>Telp.</td>
                                <td>:</td>
                                <td class="dotted">
                                    {{ optional($chunkItems->first()->creditors)->phone ?? '-' }}
                                </td>
                            </tr>
                        </table>

                        <div class="section-gap">Dengan Obat-Obat Tertentu yang dipesan adalah :</div>

                        {{-- TABLE WRAPPER FIXED HEIGHT --}}
                        <div class="table-fixed-wrap">
                            <table class="form-table">
                                <thead>
                                    <tr>
                                        <th style="width:5%;">No.</th>
                                        <th style="width:28%;">NAMA OBAT</th>
                                        <th style="width:36%;">KOMPOSISI</th>
                                        <th style="width:16%;">BENTUK & KEKUATAN SEDIAAN</th>
                                        <th style="width:15%;">JUMLAH</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($chunkItems as $index => $row)
                                        @php
                                            $komposisi = $row->medicines->component ?? '-';
                                            $bentukKekuatan = trim(
                                                ($row->medicines->unit ?: $row->medicines->packaging ?: '') .
                                                    ' ' .
                                                    ($row->medicines->dosage ?? ''),
                                            );
                                            $qty = $row->quantity;
                                            $kemasan = $row->medicines->packaging
                                                ? ' ' . $row->medicines->packaging
                                                : '';
                                            $terbilang = strtolower(terbilang($qty));
                                        @endphp
                                        <tr>
                                            <td style="text-align:center;">{{ $chunkIndex * $itemsPerPage + $loop->iteration }}
                                            </td>
                                            <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                            <td style="text-align:center; font-size:8px; line-height:1.15;">
                                                {{ $komposisi }}</td>
                                            <td style="text-align:center;">{{ $bentukKekuatan ?: '-' }}</td>
                                            <td style="text-align:center;">{{ $qty }}
                                                ({{ $terbilang }}){{ $kemasan }}</td>
                                        </tr>
                                    @endforeach

                                    @for ($i = count($chunkItems); $i < 11; $i++)
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
                        </div>

                        <div class="section-gap">Obat-Obat Tertentu tersebut akan dipergunakan untuk :</div>

                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:75px; vertical-align:top;">Nama Sarana</td>
                                <td style="width:8px; vertical-align:top;">:</td>
                                <td>
                                    <b>{{ $pharmacy->name }}</b>
                                </td>
                            </tr>
                            <tr>
                                <td style="vertical-align:top;">Alamat Sarana</td>
                                <td style="vertical-align:top;">:</td>
                                <td>{{ $pharmacy->address }}{{ $pharmacy->city ? ' ' . $pharmacy->city : '' }}</td>
                            </tr>
                            <tr>
                                <td style="vertical-align:top;">No. SIA</td>
                                <td style="vertical-align:top;">:</td>
                                <td>{{ $pharmacy->permit }}</td>
                            </tr>
                        </table>

                        <table class="signature-block" style="width:100%;">
                            <tr>
                                <td style="width:42%;"></td>
                                <td style="width:58%; text-align:center;">
                                    {{ $pharmacy->city }}, {{ $date }}
                                    <br>
                                    Pemesan
                                    @if (!empty($signatureBase64))
                                        <div style="margin: 1px 0;">
                                            <img src="{{ $signatureBase64 }}" style="height:35px; width:auto;">
                                        </div>
                                    @elseif ($pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature)))
                                        <div style="margin: 1px 0;">
                                            <img src="{{ public_path('img/' . $pharmacy->signature) }}"
                                                style="height:35px; width:auto;">
                                        </div>
                                    @else
                                        <div style="height:35px;"></div> {{-- blank space to sign by hand --}}
                                    @endif
                                    <b><u>( {{ $pharmacy->pharmacist }} )</u></b><br>
                                    SIPA : {{ $pharmacy->pharmacist_permit }}
                                </td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            @endforeach

            {{-- =========================================================
            4) NARKOTIKA (1 Obat 1 SP)
            ========================================================== --}}
        @elseif (str_starts_with($type, 'NARKOTIKA'))
            @foreach ($items as $creditorCode => $creditorItems)
                @if (!$isFirstPage)
                    <div style="page-break-before: always;"></div>
                @endif
                @php
                    $isFirstPage = false;
                    $spCode = $creditorItems->first()->order_items_code;
                @endphp
                <div class="non-regular-page">
                    <div class="title-main">
                        SURAT PESANAN NARKOTIKA
                    </div>
                    <div class="subtitle">
                        Nomor : {{ $spCode }}
                    </div>

                    {{-- IDENTITAS --}}
                    <div class="section-gap">Yang bertanda tangan dibawah ini :</div>
                    <table class="id-table" style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:75px;">N a m a</td>
                            <td style="width:8px;">:</td>
                            <td><b>{{ $pharmacy->pharmacist }}</b></td>
                        </tr>
                        <tr>
                            <td>Jabatan</td>
                            <td>:</td>
                            <td>Apoteker Pengelola Apotek</td>
                        </tr>
                    </table>

                    <div class="section-gap">Mengajukan pesanan Narkotika kepada :</div>
                    <table class="id-table" style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:90px;">Nama Distributor</td>
                            <td style="width:8px;">:</td>
                            <td class="dotted"><b>{{ optional($creditorItems->first()->creditors)->name ?? '-' }}</b>
                            </td>
                        </tr>
                        <tr>
                            <td>Alamat</td>
                            <td>:</td>
                            <td class="dotted">{{ optional($creditorItems->first()->creditors)->address ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Telp.</td>
                            <td>:</td>
                            <td class="dotted">{{ optional($creditorItems->first()->creditors)->phone ?? '-' }}</td>
                        </tr>
                    </table>

                    {{-- TABLE TITLE --}}
                    <div class="section-gap">
                        Dengan Narkotika yang dipesan adalah :
                    </div>

                    {{-- TABLE WRAPPER FIXED HEIGHT --}}
                    <div class="table-fixed-wrap">
                        <table class="form-table">
                            <thead>
                                <tr>
                                    <th style="width:5%;">No.</th>
                                    <th style="width:28%;">NAMA OBAT</th>
                                    <th style="width:36%;">KOMPOSISI</th>
                                    <th style="width:16%;">BENTUK & KEKUATAN SEDIAAN</th>
                                    <th style="width:15%;">JUMLAH</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($creditorItems as $index => $row)
                                    @php
                                        $komposisi = $row->medicines->component ?? '-';
                                        $bentukKekuatan = trim(
                                            ($row->medicines->unit ?: $row->medicines->packaging ?: '') .
                                                ' ' .
                                                ($row->medicines->dosage ?? ''),
                                        );
                                        $qty = $row->quantity;
                                        $kemasan = $row->medicines->packaging ? ' ' . $row->medicines->packaging : '';
                                        $terbilang = strtolower(terbilang($qty));
                                    @endphp
                                    <tr>
                                        <td style="text-align:center;">{{ $index + 1 }}</td>
                                        <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                        <td style="text-align:center; font-size:8px; line-height:1.15;">
                                            {{ $komposisi }}</td>
                                        <td style="text-align:center;">{{ $bentukKekuatan ?: '-' }}</td>
                                        <td style="text-align:center;">{{ $qty }}
                                            ({{ $terbilang }}){{ $kemasan }}</td>
                                    </tr>
                                @endforeach

                                @for ($i = count($creditorItems); $i < 7; $i++)
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
                    </div>

                    {{-- SARANA --}}
                    <div class="section-gap">Narkotika tersebut akan dipergunakan untuk :</div>
                    <table class="id-table" style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:75px; vertical-align:top;">Nama Sarana</td>
                            <td style="width:8px; vertical-align:top;">:</td>
                            <td><b>{{ $pharmacy->name }}</b></td>
                        </tr>
                        <tr>
                            <td style="vertical-align:top;">Alamat Sarana</td>
                            <td style="vertical-align:top;">:</td>
                            <td>{{ $pharmacy->address }}{{ $pharmacy->city ? ' ' . $pharmacy->city : '' }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align:top;">No. SIA</td>
                            <td style="vertical-align:top;">:</td>
                            <td>{{ $pharmacy->permit }}</td>
                        </tr>
                    </table>

                    {{-- SIGNATURE --}}
                    <table class="signature-block" style="width:100%;">
                        <tr>
                            <td style="width:42%;"></td>
                            <td style="width:58%; text-align:center;">
                                {{ $pharmacy->city }}, {{ $date }}
                                <br>
                                Pemesan
                                @if (!empty($signatureBase64))
                                    <div style="margin: 1px 0;">
                                        <img src="{{ $signatureBase64 }}" style="height:35px; width:auto;">
                                    </div>
                                @elseif ($pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature)))
                                    <div style="margin: 1px 0;">
                                        <img src="{{ public_path('img/' . $pharmacy->signature) }}"
                                            style="height:35px; width:auto;">
                                    </div>
                                @else
                                    <div style="height:35px;"></div> {{-- blank space to sign by hand --}}
                                @endif
                                <b><u>( {{ $pharmacy->pharmacist }} )</u></b><br>
                                SIPA : {{ $pharmacy->pharmacist_permit }}
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach

            {{-- =========================================================
            5) PSIKOTROPIKA
            ========================================================== --}}
        @elseif ($type == 'Psikotropika' || $type == 'PSIKOTROPIKA')
            @foreach ($items as $creditorCode => $creditorItems)
                @php
                    $spCode = $creditorItems->first()->order_items_code;
                    $itemsPerPage = 11;
                    $chunks = $creditorItems->chunk($itemsPerPage);
                    $totalChunks = $chunks->count();
                @endphp
                @foreach ($chunks as $chunkIndex => $chunkItems)
                    @if (!$isFirstPage)
                        <div style="page-break-before: always;"></div>
                    @endif
                    @php $isFirstPage = false; @endphp

                    <div class="non-regular-page">

                        <div class="title-main">
                            SURAT PESANAN PSIKOTROPIKA
                        </div>
                        <div class="subtitle">
                            Nomor :
                            {{ $spCode }}{{ $totalChunks > 1 ? ' (Hal ' . ($chunkIndex + 1) . ')' : '' }}
                        </div>

                        {{-- IDENTITAS --}}
                        <div class="section-gap">Yang bertanda tangan dibawah ini :</div>
                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:75px;">N a m a</td>
                                <td style="width:8px;">:</td>
                                <td><b>{{ $pharmacy->pharmacist }}</b></td>
                            </tr>
                            <tr>
                                <td>Jabatan</td>
                                <td>:</td>
                                <td>Apoteker Pengelola Apotek</td>
                            </tr>
                        </table>

                        <div class="section-gap">Mengajukan pesanan Psikotropika kepada :</div>
                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:90px;">Nama Distributor</td>
                                <td style="width:8px;">:</td>
                                <td class="dotted"><b>{{ optional($chunkItems->first()->creditors)->name ?? '-' }}</b>
                                </td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>:</td>
                                <td class="dotted">{{ optional($chunkItems->first()->creditors)->address ?? '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td>Telp.</td>
                                <td>:</td>
                                <td class="dotted">{{ optional($chunkItems->first()->creditors)->phone ?? '-' }}</td>
                            </tr>
                        </table>

                        {{-- TABLE TITLE --}}
                        <div class="section-gap">
                            Dengan Psikotropika yang dipesan adalah :
                        </div>

                        {{-- TABLE --}}
                        {{-- TABLE WRAPPER FIXED HEIGHT --}}
                        <div class="table-fixed-wrap">
                            <table class="form-table">
                                <thead>
                                    <tr>
                                        <th style="width:5%;">No.</th>
                                        <th style="width:28%;">NAMA OBAT</th>
                                        <th style="width:36%;">KOMPOSISI</th>
                                        <th style="width:16%;">BENTUK & KEKUATAN SEDIAAN</th>
                                        <th style="width:15%;">JUMLAH</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($chunkItems as $index => $row)
                                        @php
                                            $komposisi = $row->medicines->component ?? '-';
                                            $bentukKekuatan = trim(
                                                ($row->medicines->unit ?: $row->medicines->packaging ?: '') .
                                                    ' ' .
                                                    ($row->medicines->dosage ?? ''),
                                            );
                                            $qty = $row->quantity;
                                            $kemasan = $row->medicines->packaging
                                                ? ' ' . $row->medicines->packaging
                                                : '';
                                            $terbilang = strtolower(terbilang($qty));
                                        @endphp
                                        <tr>
                                            <td style="text-align:center;">{{ $chunkIndex * $itemsPerPage + $loop->iteration }}
                                            </td>
                                            <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                            <td style="text-align:center; font-size:8px; line-height:1.15;">
                                                {{ $komposisi }}</td>
                                            <td style="text-align:center;">{{ $bentukKekuatan ?: '-' }}</td>
                                            <td style="text-align:center;">{{ $qty }}
                                                ({{ $terbilang }}){{ $kemasan }}</td>
                                        </tr>
                                    @endforeach

                                    @for ($i = count($chunkItems); $i < 11; $i++)
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
                        </div>

                        {{-- SARANA --}}
                        <div class="section-gap">Psikotropika tersebut akan dipergunakan untuk :</div>
                        <table class="id-table" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:75px; vertical-align:top;">Nama Sarana</td>
                                <td style="width:8px; vertical-align:top;">:</td>
                                <td><b>{{ $pharmacy->name }}</b></td>
                            </tr>
                            <tr>
                                <td style="vertical-align:top;">Alamat Sarana</td>
                                <td style="vertical-align:top;">:</td>
                                <td>{{ $pharmacy->address }}{{ $pharmacy->city ? ' ' . $pharmacy->city : '' }}</td>
                            </tr>
                            <tr>
                                <td style="vertical-align:top;">No. SIA</td>
                                <td style="vertical-align:top;">:</td>
                                <td>{{ $pharmacy->permit }}</td>
                            </tr>
                        </table>

                        {{-- SIGNATURE --}}
                        <table class="signature-block" style="width:100%;">
                            <tr>
                                <td style="width:42%;"></td>
                                <td style="width:58%; text-align:center;">
                                    {{ $pharmacy->city }}, {{ $date }}
                                    <br>
                                    Pemesan
                                    @if (!empty($signatureBase64))
                                        <div style="margin: 1px 0;">
                                            <img src="{{ $signatureBase64 }}" style="height:35px; width:auto;">
                                        </div>
                                    @elseif ($pharmacy->signature && file_exists(public_path('img/' . $pharmacy->signature)))
                                        <div style="margin: 1px 0;">
                                            <img src="{{ public_path('img/' . $pharmacy->signature) }}"
                                                style="height:35px; width:auto;">
                                        </div>
                                    @else
                                        <div style="height:35px;"></div> {{-- blank space to sign by hand --}}
                                    @endif
                                    <b><u>( {{ $pharmacy->pharmacist }} )</u></b><br>
                                    SIPA : {{ $pharmacy->pharmacist_permit }}
                                </td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            @endforeach
        @endif
    @endforeach

</body>

</html>
