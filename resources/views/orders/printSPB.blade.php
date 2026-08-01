<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Surat Pesanan</title>
    <style>
        @page {
            margin: 15px 25px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.4;
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
            border-top: 2px solid #000;
            margin-top: 8px;
            margin-bottom: 12px;
        }

        /* Styling Tabel Utama */
        table.form-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.form-table th,
        table.form-table td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 11px;
        }

        table.form-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        /* Identitas / Form List */
        .id-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        /* Catatan Kaki */
        .notes {
            font-size: 11px;
            margin-top: 20px;
            border-top: 1px dashed #ccc;
            padding-top: 5px;
        }

        /* Mencegah TTD terpotong ke halaman baru */
        .signature-block {
            margin-top: 20px;
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    @foreach ($grouped as $type => $items)
        @if (!$loop->first)
            <div style="page-break-before: always;"></div>
        @endif

        {{-- =========================================================
         1) REGULER
    ========================================================== --}}
        @if ($type == 'REGULER')
            @foreach ($items as $creditorCode => $items)
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:110px; vertical-align:top;">
                            <img src="{{ public_path('img/logo-shb.png') }}" style="width:90px; height:auto;">
                        </td>
                        <td style="vertical-align:top; padding-left:5px;">
                            <h2 style="margin:0; font-size:18px;">APOTEK SAHABAT</h2>
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
                <table style="width:100%; margin-top:10px;">
                    <tr>
                        <td style="width:50%; vertical-align:top;">
                            <b>No :</b> SP-00{{ $items->first()->id }}
                        </td>
                        <td style="width:50%; text-align:right; vertical-align:top;">
                            <b>Kepada Yth :</b> {{ optional($items->first()->creditors)->name ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align:right;">
                            Di- {{ optional($items->first()->creditors)->city ?? '-' }}
                        </td>
                    </tr>
                </table>

                {{-- TITLE --}}
                <div
                    style="text-align:center; font-weight:bold; font-size:18px; text-decoration:underline; margin-top:15px; margin-bottom:10px;">
                    SURAT PESANAN
                </div>

                <div style="margin-bottom:8px;">Mohon dikirim obat-obatan untuk keperluan apotek :</div>

                {{-- TABLE --}}
                <table class="form-table">
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
                                    {{ $row->quantity }}
                                </td>
                                <td>
                                    {{ $row->medicines->name ?? '-' }}
                                </td>
                                <td>
                                    {{-- optional --}}
                                </td>
                            </tr>
                        @endforeach

                        @for ($i = count($items); $i < 12; $i++)
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
                        <td style="width:45%; vertical-align:top; font-size:11px; color:#444;">
                            Asli : Arsip PBF <br>
                            Copy : Arsip Apotek
                        </td>
                        <td style="width:55%; text-align:right; vertical-align:top;">
                            Samarinda, {{ $date }}
                            <br>
                            Penanggung Jawab,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:65px; width:auto;">
                            </div>
                            <b><u>apt. Nurlina Muliani, S.Farm., M. Farm</u></b><br>
                            <span style="font-size:11px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach

            {{-- =========================================================
         2) PREKURSOR
    ========================================================== --}}
        @elseif ($type == 'PREKURSOR')
            @foreach ($items as $creditorCode => $items)
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:110px; vertical-align:top;">
                            <img src="{{ public_path('img/logo-shb.png') }}" style="width:90px; height:auto;">
                        </td>
                        <td style="vertical-align:top; padding-left:5px;">
                            <h2 style="margin:0; font-size:18px;">APOTEK SAHABAT</h2>
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
                <table style="width:100%; margin-top:10px;">
                    <tr>
                        <td style="width:50%; vertical-align:top;">
                            <b>No :</b> SP-00{{ $items->first()->id }}
                        </td>
                        <td style="width:50%; text-align:right; vertical-align:top;">
                            <b>Kepada Yth :</b> {{ optional($items->first()->creditors)->name ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align:right;">
                            Di- {{ optional($items->first()->creditors)->city ?? '-' }}
                        </td>
                    </tr>
                </table>

                {{-- TITLE --}}
                <div
                    style="text-align:center; font-weight:bold; font-size:18px; text-decoration:underline; margin-top:15px; margin-bottom:10px;">
                    SURAT PESANAN PREKURSOR
                </div>

                <div style="margin-bottom:8px;">Mohon dikirim obat-obatan untuk keperluan apotek :</div>

                {{-- TABLE --}}
                <table class="form-table">
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
                                    {{ $row->quantity }}
                                </td>
                                <td>
                                    {{ $row->medicines->name ?? '-' }}
                                </td>
                                <td>
                                    {{-- optional --}}
                                </td>
                            </tr>
                        @endforeach

                        @for ($i = count($items); $i < 12; $i++)
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
                        <td style="width:45%; vertical-align:top; font-size:11px; color:#444;">
                            Asli : Arsip PBF <br>
                            Copy : Arsip Apotek
                        </td>
                        <td style="width:55%; text-align:right; vertical-align:top;">
                            Samarinda, {{ $date }}
                            <br>
                            Penanggung Jawab,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:65px; width:auto;">
                            </div>
                            <b><u>apt. Nurlina Muliani, S.Farm., M. Farm</u></b><br>
                            <span style="font-size:11px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach

            {{-- =========================================================
         3) OBAT-OBAT TERTENTU (OOT)
    ========================================================== --}}
        @elseif ($type == 'Obat Tertentu' || $type == 'OBAT-OBAT TERTENTU (OOT)')
            @foreach ($items as $creditorCode => $items)
                <div style="text-align:center; font-weight:bold; font-size:16px; text-decoration:underline;">
                    SURAT PESANAN OBAT-OBAT TERTENTU
                </div>

                <div style="text-align:center; margin-top:4px;">
                    Nomor : SP-00{{ $items->first()->id }}
                </div>

                <div style="margin-top:15px; margin-bottom:8px;">Yang bertanda tangan dibawah ini :</div>

                <table class="id-table" style="width:100%; border-collapse:collapse; margin-bottom:10px;">
                    <tr>
                        <td style="width:130px; padding:3px 0;">Nama</td>
                        <td style="width:10px;">:</td>
                        <td style="border-bottom:1px dotted #000; padding:3px 0;">
                            <b>apt. Nurlina Muliani, S.Farm., M.Farm</b>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0;">Jabatan</td>
                        <td>:</td>
                        <td style="border-bottom:1px dotted #000; padding:3px 0;">
                            Apoteker Pengelola Apotek
                        </td>
                    </tr>
                </table>

                <div style="margin-top:12px; margin-bottom:8px;">Mengajukan pesanan Obat-Obat Tertentu kepada :</div>

                <table class="id-table" style="width:100%; border-collapse:collapse; margin-bottom:10px;">
                    <tr>
                        <td style="width:130px; padding:3px 0;">Nama Distributor</td>
                        <td style="width:10px;">:</td>
                        <td style="border-bottom:1px dotted #000; padding:3px 0;">
                            <b>{{ optional($items->first()->creditors)->name ?? '-' }}</b>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0;">Alamat</td>
                        <td>:</td>
                        <td style="border-bottom:1px dotted #000; padding:3px 0;">
                            {{ optional($items->first()->creditors)->address ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0;">Telp.</td>
                        <td>:</td>
                        <td style="border-bottom:1px dotted #000; padding:3px 0;">
                            {{ optional($items->first()->creditors)->phone ?? '-' }}
                        </td>
                    </tr>
                </table>

                <div style="margin-top:12px; margin-bottom:8px;">Dengan Obat-Obat Tertentu yang dipesan adalah :</div>

                <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
                    @php $no = 1; @endphp
                    @foreach ($items as $row)
                        <tr>
                            <td style="width:30px; padding:4px 0;">{{ $no++ }})</td>
                            <td style="border-bottom:1px dotted #000; padding:4px 0;">
                                <b>{{ $row->medicines->name ?? '-' }}</b>
                                ({{ $row->quantity }})
                            </td>
                        </tr>
                    @endforeach

                    @for ($i = count($items); $i < 4; $i++)
                        <tr>
                            <td style="padding:4px 0;">{{ $i + 1 }})</td>
                            <td style="border-bottom:1px dotted #000; padding:4px 0;">&nbsp;</td>
                        </tr>
                    @endfor
                </table>

                <div style="margin-top:12px; margin-bottom:8px;">Obat-Obat Tertentu tersebut akan dipergunakan untuk :
                </div>

                <table class="id-table" style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:130px; vertical-align:top; padding:3px 0;">Nama Sarana</td>
                        <td style="width:10px; vertical-align:top; padding:3px 0;">:</td>
                        <td style="padding:3px 0;">
                            <b>Apotek Sahabat</b><br>
                            <span style="font-size:11px; color:#555;">( PBF / Apotek / Instalasi Farmasi Rumah Sakit /
                                Instalasi Farmasi Klinik )</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align:top; padding:3px 0;">Alamat Sarana</td>
                        <td style="vertical-align:top; padding:3px 0;">:</td>
                        <td style="padding:3px 0;">Jl. Palang Merah Indonesia No. 16 - B Samarinda</td>
                    </tr>
                </table>

                <table class="signature-block" style="width:100%;">
                    <tr>
                        <td style="width:50%;"></td>
                        <td style="width:50%; text-align:right;">
                            Samarinda, {{ $date }}
                            <br>
                            Pemesan,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:65px; width:auto;">
                            </div>
                            <b>( apt. Nurlina Muliani, S.Farm., M.Farm )</b><br>
                            <span style="font-size:11px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                <div class="notes">
                    <b>Catatan :</b> <br>
                    - Satu Surat pesanan hanya berlaku untuk satu jenis Obat-Obat tertentu. <br>
                    - Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.
                </div>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach

            {{-- =========================================================
         4) NARKOTIKA
    ========================================================== --}}
        @elseif ($type == 'NARKOTIKA')
            @foreach ($items as $creditorCode => $items)
                <div style="text-align:center; font-weight:bold; font-size:16px; text-decoration:underline;">
                    SURAT PESANAN NARKOTIKA
                </div>
                <div style="text-align:center; margin-top:4px;">
                    Nomor : SP-00{{ $items->first()->id }}
                </div>

                {{-- IDENTITAS --}}
                <div style="margin-top:15px; margin-bottom:5px;">Yang bertanda tangan dibawah ini :</div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama</td>
                        <td style="width:10px;">:</td>
                        <td><b>apt. Nurlina Muliani, S. Farm., M. Farm.</b></td>
                    </tr>
                    <tr>
                        <td>Jabatan</td>
                        <td>:</td>
                        <td>Apoteker Pengelola Apotek</td>
                    </tr>
                </table>

                <div style="margin-top:12px; margin-bottom:5px;">Mengajukan pesanan Narkotika kepada :</div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Distributor</td>
                        <td style="width:10px;">:</td>
                        <td><b>{{ optional($items->first()->creditors)->name ?? '-' }}</b></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td>{{ optional($items->first()->creditors)->address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Telp.</td>
                        <td>:</td>
                        <td>{{ optional($items->first()->creditors)->phone ?? '-' }}</td>
                    </tr>
                </table>

                {{-- TABLE TITLE --}}
                <div style="margin-top:12px; margin-bottom:5px;">
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
                                <td style="text-align:center;">{{ $index + 1 }}</td>
                                <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                <td style="text-align:center;">{{ $row->medicines->form ?? '-' }}</td>
                                <td style="text-align:center;">{{ $row->medicines->strength ?? '-' }}</td>
                                <td style="text-align:center;">{{ $row->quantity }}</td>
                            </tr>
                        @endforeach

                        @for ($i = count($items); $i < 6; $i++)
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
                        <tr>
                            <td colspan="3" style="border:none;"></td>
                            <td style="text-align:center; font-weight:bold; border:1px solid #000;">Total</td>
                            <td style="text-align:center; font-weight:bold; border:1px solid #000;">Rp.
                                {{ number_format($totalprice) }}</td>
                        </tr>
                    </tfoot>
                </table>

                {{-- SARANA --}}
                <div style="margin-top:12px; margin-bottom:5px;">Narkotika tersebut akan dipergunakan untuk :</div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Sarana</td>
                        <td style="width:10px;">:</td>
                        <td><b>Apotek Sahabat</b></td>
                    </tr>
                    <tr>
                        <td>Alamat Sarana</td>
                        <td>:</td>
                        <td>Jl. Palang Merah Indonesia No. 16 - B Samarinda</td>
                    </tr>
                </table>

                {{-- SIGNATURE --}}
                <table class="signature-block" style="width:100%;">
                    <tr>
                        <td style="width:50%;"></td>
                        <td style="width:50%; text-align:center;">
                            Samarinda, {{ $date }}
                            <br>
                            Pemesan,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:65px; width:auto;">
                            </div>
                            <b><u>apt. Nurlina Muliani, S. Farm., M. Farm.</u></b><br>
                            <span style="font-size:11px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                {{-- NOTES --}}
                <div class="notes">
                    <b>Catatan :</b> <br>
                    - Satu Surat pesanan hanya berlaku untuk satu jenis Narkotika. <br>
                    - Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.
                </div>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach

            {{-- =========================================================
         5) PSIKOTROPIKA
    ========================================================== --}}
        @elseif ($type == 'Psikotropika' || $type == 'PSIKOTROPIKA')
            @foreach ($items as $creditorCode => $items)
                <div style="text-align:center; font-weight:bold; font-size:16px; text-decoration:underline;">
                    SURAT PESANAN PSIKOTROPIKA
                </div>
                <div style="text-align:center; margin-top:4px;">
                    Nomor : SP-00{{ $items->first()->id }}
                </div>

                {{-- IDENTITAS --}}
                <div style="margin-top:15px; margin-bottom:5px;">Yang bertanda tangan dibawah ini :</div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama</td>
                        <td style="width:10px;">:</td>
                        <td><b>apt. Nurlina Muliani, S. Farm., M. Farm</b></td>
                    </tr>
                    <tr>
                        <td>Jabatan</td>
                        <td>:</td>
                        <td>Apoteker Pengelola Apotek</td>
                    </tr>
                </table>

                <div style="margin-top:12px; margin-bottom:5px;">Mengajukan pesanan Psikotropika kepada :</div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Distributor</td>
                        <td style="width:10px;">:</td>
                        <td><b>{{ optional($items->first()->creditors)->name ?? '-' }}</b></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td>{{ optional($items->first()->creditors)->address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Telp.</td>
                        <td>:</td>
                        <td>{{ optional($items->first()->creditors)->phone ?? '-' }}</td>
                    </tr>
                </table>

                {{-- TABLE TITLE --}}
                <div style="margin-top:12px; margin-bottom:5px;">
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
                                <td style="text-align:center;">{{ $index + 1 }}</td>
                                <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                <td style="text-align:center;">{{ $row->medicines->form ?? '-' }}</td>
                                <td style="text-align:center;">{{ $row->medicines->strength ?? '-' }}</td>
                                <td style="text-align:center;">{{ $row->quantity }}</td>
                            </tr>
                        @endforeach

                        @for ($i = count($items); $i < 6; $i++)
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
                <div style="margin-top:12px; margin-bottom:5px;">Psikotropika tersebut akan dipergunakan untuk :</div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Sarana</td>
                        <td style="width:10px;">:</td>
                        <td><b>Apotek Sahabat</b></td>
                    </tr>
                    <tr>
                        <td>Alamat Sarana</td>
                        <td>:</td>
                        <td>Jl. Palang Merah Indonesia No. 16 - B Samarinda</td>
                    </tr>
                </table>

                {{-- SIGNATURE --}}
                <table class="signature-block" style="width:100%;">
                    <tr>
                        <td style="width:50%;"></td>
                        <td style="width:50%; text-align:center;">
                            Samarinda, {{ $date }}
                            <br>
                            Pemesan,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:65px; width:auto;">
                            </div>
                            <b><u>apt. Nurlina Muliani, S. Farm., M. Farm</u></b><br>
                            <span style="font-size:11px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                {{-- NOTES --}}
                <div class="notes">
                    <b>Catatan :</b> <br>
                    - Satu Surat pesanan hanya berlaku untuk satu jenis Psikotropika. <br>
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
