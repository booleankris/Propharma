<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Surat Pesanan Final</title>
    <style>
        @page {
            margin: 15px 35px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6px;
            line-height: 1.2;
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
            border-top: 1px solid #000;
            margin-top: 3px;
            margin-bottom: 4px;
        }

        /* Styling Tabel Utama */
        table.form-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }

        table.form-table th,
        table.form-table td {
            border: 0.3px solid #000;
            padding: 2.5px;
            font-size: 4px;
        }

        table.form-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        /* Identitas / Form List */
        .id-table td {
            padding: 1px 0;
            vertical-align: top;
            font-size: 5px;
        }

        .id-table td.dotted {
            border-bottom: 1px dotted #000;
            padding: 0 0 1px 0;
        }

        /* Catatan Kaki */
        .notes {
            font-size: 5.5px;
            margin-top: 4px;
            padding-top: 2px;
        }

        /* Mencegah TTD terpotong ke halaman baru */
        .signature-block {
            margin-top: 8px;
            page-break-inside: avoid;
            font-size: 4.8px;
        }

        .signature-block td {
            font-size: 4.8px;
            line-height: 1.2;
        }

        .section-gap {
            margin-top: 3px;
            margin-bottom: 2px;
        }

        .title-main {
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            text-decoration: underline;
            margin-top: 3px;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            margin-top: 1px;
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
                @foreach ($creditorItems->chunk(15) as $chunkIndex => $chunkItems)
                    @if (!$isFirstPage)
                        <div style="page-break-before: always;"></div>
                    @endif
                    @php $isFirstPage = false; @endphp

                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:38px; vertical-align:middle;">
                                @if (!empty($logoBase64))
                                    <img src="{{ $logoBase64 }}" style="width:40px; height:auto;">
                                @elseif ($pharmacy->logo && file_exists(public_path('img/' . $pharmacy->logo)))
                                    <img src="{{ public_path('img/' . $pharmacy->logo) }}"
                                        style="width:40px; height:auto;">
                                @else
                                    <img src="{{ public_path('img/logo-shb.png') }}" style="width:40px; height:auto;">
                                @endif
                            </td>
                            <td style="vertical-align:top; padding-left:3px;">
                                <h2 style="margin:0; font-size:8px;">{{ strtoupper($pharmacy->name) }}</h2>
                                <div>{{ $pharmacy->address }}</div>
                                <div>HP. {{ $pharmacy->phone }}</div>
                                <div>Apoteker : {{ $pharmacy->pharmacist }}</div>
                                <div>No. SIPA : {{ $pharmacy->pharmacist_permit }}</div>
                                @if ($pharmacy->permit)
                                    <div>No. SIA : {{ $pharmacy->permit }}</div>
                                @elseif ($pharmacy->pharmacy_registration)
                                    <div>No. STR : {{ $pharmacy->pharmacy_registration }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>

                    <div class="line"></div>

                    {{-- NO & KEPADA --}}
                    @php
                        $spCode = $creditorItems->first()->order_items_code;
                    @endphp
                    <table style="width:100%; margin-top:2px;">
                        <tr>
                            <td style="width:50%; vertical-align:top;">
                                <b>No :</b>
                                {{ $spCode }}{{ $chunkIndex > 0 ? ' (Hal ' . ($chunkIndex + 1) . ')' : '' }}
                            </td>
                            <td style="width:50%; text-align:right; font-size: 6px; vertical-align:top;">
                                <b>Kepada Yth :</b> {{ optional($creditorItems->first()->creditors)->name ?? '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align:right;font-size:6px;">
                                Di- {{ optional($creditorItems->first()->creditors)->address ?? '-' }}
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
                                <th style="width:20%;">Jumlah</th>
                                <th style="width:55%;">Nama Obat</th>
                                <th style="width:25%;">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($chunkItems as $row)
                                <tr>
                                    <td style="text-align:center;">
                                        {{ $row->receivingItems->sum('qty_received') }}
                                        ({{ ucfirst(terbilang($row->receivingItems->sum('qty_received'))) }})
                                        {{ $row->medicines->packaging ?? '-' }}
                                    </td>
                                    <td>
                                        {{ $row->medicines->name ?? '-' }}
                                    </td>
                                    <td>
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

                            @for ($i = count($chunkItems); $i < 15; $i++)
                                <tr>
                                    <td>&nbsp;</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>

                    {{-- FOOTER --}}
                    <table class="signature-block" style="width:100%;">
                        <tr>
                            <td style="width:35%; vertical-align:top; color:#444;">
                                Asli : Arsip PBF <br>
                                Copy : Arsip Apotek
                            </td>
                            <td style="width:65%; text-align:right; vertical-align:top;">
                                {{ $pharmacy->city }}, {{ $date }}
                                <br>
                                Penanggung Jawab,

                                @if (!empty($signatureBase64))
                                    <div style="margin: 1px 0;">
                                        <img src="{{ $signatureBase64 }}"
                                            style="height:35px; width:auto;">
                                    </div>
                                @elseif ($pharmacy->signature && file_exists(public_path('img/'.$pharmacy->signature)))
                                    <div style="margin: 1px 0;">
                                        <img src="{{ public_path('img/'.$pharmacy->signature) }}"
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
                @endforeach
            @endforeach

            {{-- =========================================================
            2) PREKURSOR
            ========================================================== --}}
        @elseif ($type == 'PREKURSOR')
            @foreach ($items as $creditorCode => $items)
                @if (!$isFirstPage)
                    <div style="page-break-before: always;"></div>
                @endif
                @php $isFirstPage = false; @endphp

                <div class="title-main">
                    SURAT PESANAN OBAT MENGANDUNG PREKURSOR FARMASI
                </div>
                <div class="subtitle">
                    Nomor SP : {{ $items->first()->order_items_code }}
                </div>

                <div class="section-gap">Yang bertanda tangan dibawah ini :</div>

                <table class="id-table" style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:60px;">Nama Apoteker</td>
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
                        <td style="width:60px;">Nama PBF</td>
                        <td style="width:8px;">:</td>
                        <td class="dotted">
                            <b>{{ optional($items->first()->creditors)->name ?? '-' }}</b>
                        </td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td class="dotted">
                            {{ optional($items->first()->creditors)->address ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td>No. Telp.</td>
                        <td>:</td>
                        <td class="dotted">
                            {{ optional($items->first()->creditors)->phone ?? '-' }}
                        </td>
                    </tr>
                </table>

                <div class="section-gap">
                    Obat mengandung Prekursor Farmasi tersebut akan digunakan untuk memenuhi kebutuhan :
                </div>

                {{-- TABLE --}}
                <table class="form-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">No.</th>
                            <th style="width:30%;">Nama Obat Mengandung Prekursor Farmasi</th>
                            <th style="width:25%;">Zat Aktif Prekursor Farmasi</th>
                            <th style="width:15%;">Satuan</th>
                            <th style="width:12%;">Jumlah</th>
                            <th style="width:13%;">Ket.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr>
                                <td style="text-align:center;">{{ $index + 1 }}</td>
                                <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                <td style="text-align:center;">
                                    {{ optional($row->medicines->composition)->name ?? '-' }}
                                </td>
                                <td style="text-align:center;">{{ $row->medicines->packaging ?? '-' }}</td>
                                <td style="text-align:center;">
                                    {{ $row->receivingItems->sum('qty_received') }}
                                    ({{ ucfirst(terbilang($row->receivingItems->sum('qty_received'))) }})
                                </td>
                                <td></td>
                            </tr>
                        @endforeach

                        @for ($i = count($items); $i < 8; $i++)
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

                <div class="section-gap">
                    Obat tersebut mengandung Prekursor tersebut akan digunakan untuk memenuhi kebutuhan:
                </div>

                <table class="id-table" style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:60px;">Nama Apotek</td>
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
                                No. SIA : {{ $pharmacy->permit }}
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
                                    <img src="{{ $signatureBase64 }}"
                                        style="height:35px; width:auto;">
                                </div>
                            @elseif ($pharmacy->signature && file_exists(public_path('img/'.$pharmacy->signature)))
                                <div style="margin: 1px 0;">
                                    <img src="{{ public_path('img/'.$pharmacy->signature) }}"
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
            @endforeach

            {{-- =========================================================
            3) OBAT-OBAT TERTENTU (OOT)
            ========================================================== --}}
        @elseif ($type == 'Obat Tertentu' || $type == 'OBAT-OBAT TERTENTU (OOT)')
            @foreach ($items as $creditorCode => $creditorItems)
                @php
                    $spCode = $creditorItems->first()->order_items_code;
                    $chunks = $creditorItems->chunk(3);
                    $totalChunks = $chunks->count();
                @endphp
                @foreach ($chunks as $chunkIndex => $chunkItems)
                    @if (!$isFirstPage)
                        <div style="page-break-before: always;"></div>
                    @endif
                    @php $isFirstPage = false; @endphp

                    <div class="title-main">
                        SURAT PESANAN OBAT-OBAT TERTENTU
                    </div>

                    <div class="subtitle">
                        Nomor : {{ $spCode }}{{ $totalChunks > 1 ? ' (Hal ' . ($chunkIndex + 1) . ')' : '' }}
                    </div>

                    <div class="section-gap">Yang bertanda tangan dibawah ini :</div>

                    <table class="id-table" style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:60px;">N a m a</td>
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

                    <table class="form-table">
                        <thead>
                            <tr>
                                <th style="width:5%;">No.</th>
                                <th style="width:30%;">NAMA OBAT</th>
                                <th style="width:25%;">KOMPOSISI</th>
                                <th style="width:20%;">BENTUK & KEKUATAN SEDIAAN</th>
                                <th style="width:20%;">JUMLAH</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($chunkItems as $index => $row)
                                @php
                                    $komposisi =
                                        $row->medicines->component ?: $row->medicines->composition->name ?? '-';
                                    $bentukKekuatan = trim(
                                        ($row->medicines->unit ?: $row->medicines->packaging ?: '') .
                                            ' ' .
                                            ($row->medicines->dosage ?? ''),
                                    );
                                    $qty = $row->receivingItems->sum('qty_received');
                                    $kemasan = $row->medicines->packaging ? ' ' . $row->medicines->packaging : '';
                                    $terbilang = strtolower(terbilang($qty));
                                @endphp
                                <tr>
                                    <td style="text-align:center;">{{ $chunkIndex * 3 + $index + 1 }}</td>
                                    <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                    <td style="text-align:center;">{{ $komposisi }}</td>
                                    <td style="text-align:center;">{{ $bentukKekuatan ?: '-' }}</td>
                                    <td style="text-align:center;">{{ $qty }}
                                        ({{ $terbilang }}){{ $kemasan }}</td>
                                </tr>
                            @endforeach

                            @for ($i = count($chunkItems); $i < 8; $i++)
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
                                        <img src="{{ $signatureBase64 }}"
                                            style="height:35px; width:auto;">
                                    </div>
                                @elseif ($pharmacy->signature && file_exists(public_path('img/'.$pharmacy->signature)))
                                    <div style="margin: 1px 0;">
                                        <img src="{{ public_path('img/'.$pharmacy->signature) }}"
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
                        <td style="width:60px;">N a m a</td>
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
                        <td class="dotted"><b>{{ optional($creditorItems->first()->creditors)->name ?? '-' }}</b></td>
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

                {{-- TABLE --}}
                <table class="form-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">No.</th>
                            <th style="width:30%;">NAMA OBAT</th>
                            <th style="width:25%;">KOMPOSISI</th>
                            <th style="width:20%;">BENTUK & KEKUATAN SEDIAAN</th>
                            <th style="width:20%;">JUMLAH</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($creditorItems as $index => $row)
                            @php
                                $komposisi = $row->medicines->component ?: $row->medicines->composition->name ?? '-';
                                $bentukKekuatan = trim(
                                    ($row->medicines->unit ?: $row->medicines->packaging ?: '') .
                                        ' ' .
                                        ($row->medicines->dosage ?? ''),
                                );
                                $qty = $row->receivingItems->sum('qty_received');
                                $kemasan = $row->medicines->packaging ? ' ' . $row->medicines->packaging : '';
                                $terbilang = strtolower(terbilang($qty));
                            @endphp
                            <tr>
                                <td style="text-align:center;">{{ $index + 1 }}</td>
                                <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                <td style="text-align:center;">{{ $komposisi }}</td>
                                <td style="text-align:center;">{{ $bentukKekuatan ?: '-' }}</td>
                                <td style="text-align:center;">{{ $qty }}
                                    ({{ $terbilang }}){{ $kemasan }}</td>
                            </tr>
                        @endforeach

                        @for ($i = count($creditorItems); $i < 8; $i++)
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
                                    <img src="{{ $signatureBase64 }}"
                                        style="height:35px; width:auto;">
                                </div>
                            @elseif ($pharmacy->signature && file_exists(public_path('img/'.$pharmacy->signature)))
                                <div style="margin: 1px 0;">
                                    <img src="{{ public_path('img/'.$pharmacy->signature) }}"
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
            @endforeach

            {{-- =========================================================
            5) PSIKOTROPIKA
            ========================================================== --}}
        @elseif ($type == 'Psikotropika' || $type == 'PSIKOTROPIKA')
            @foreach ($items as $creditorCode => $creditorItems)
                @php
                    $spCode = $creditorItems->first()->order_items_code;
                    $chunks = $creditorItems->chunk(3);
                    $totalChunks = $chunks->count();
                @endphp
                @foreach ($chunks as $chunkIndex => $chunkItems)
                    @if (!$isFirstPage)
                        <div style="page-break-before: always;"></div>
                    @endif
                    @php $isFirstPage = false; @endphp

                    <div class="title-main">
                        SURAT PESANAN PSIKOTROPIKA
                    </div>
                    <div class="subtitle">
                        Nomor : {{ $spCode }}{{ $totalChunks > 1 ? ' (Hal ' . ($chunkIndex + 1) . ')' : '' }}
                    </div>

                    {{-- IDENTITAS --}}
                    <div class="section-gap">Yang bertanda tangan dibawah ini :</div>
                    <table class="id-table" style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:60px;">N a m a</td>
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
                            <td class="dotted">{{ optional($chunkItems->first()->creditors)->address ?? '-' }}</td>
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
                    <table class="form-table">
                        <thead>
                            <tr>
                                <th style="width:5%;">No.</th>
                                <th style="width:30%;">NAMA OBAT</th>
                                <th style="width:25%;">KOMPOSISI</th>
                                <th style="width:20%;">BENTUK & KEKUATAN SEDIAAN</th>
                                <th style="width:20%;">JUMLAH</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($chunkItems as $index => $row)
                                @php
                                    $komposisi =
                                        $row->medicines->component ?: $row->medicines->composition->name ?? '-';
                                    $bentukKekuatan = trim(
                                        ($row->medicines->unit ?: $row->medicines->packaging ?: '') .
                                            ' ' .
                                            ($row->medicines->dosage ?? ''),
                                    );
                                    $qty = $row->receivingItems->sum('qty_received');
                                    $kemasan = $row->medicines->packaging ? ' ' . $row->medicines->packaging : '';
                                    $terbilang = strtolower(terbilang($qty));
                                @endphp
                                <tr>
                                    <td style="text-align:center;">{{ $chunkIndex * 3 + $index + 1 }}</td>
                                    <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                    <td style="text-align:center;">{{ $komposisi }}</td>
                                    <td style="text-align:center;">{{ $bentukKekuatan ?: '-' }}</td>
                                    <td style="text-align:center;">{{ $qty }}
                                        ({{ $terbilang }}){{ $kemasan }}</td>
                                </tr>
                            @endforeach

                            @for ($i = count($chunkItems); $i < 8; $i++)
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
                                        <img src="{{ $signatureBase64 }}"
                                            style="height:35px; width:auto;">
                                    </div>
                                @elseif ($pharmacy->signature && file_exists(public_path('img/'.$pharmacy->signature)))
                                    <div style="margin: 1px 0;">
                                        <img src="{{ public_path('img/'.$pharmacy->signature) }}"
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
                @endforeach
            @endforeach
        @endif
    @endforeach

</body>

</html>
