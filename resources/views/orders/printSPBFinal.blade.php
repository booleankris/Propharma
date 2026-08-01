<style>
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 13px;
        line-height: 1.4;
        color: #000;
    }
    table {
        border-collapse: collapse;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .bold { font-weight: bold; }
    .underline { text-decoration: underline; }
</style>

@foreach ($grouped as $type => $items)
    @if (!$loop->first)
        <div style="page-break-before: always;"></div>
    @endif

    {{-- =========================================================
         1) REGULER (SURAT PESANAN REGULER)
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
                        <div>Jl. Palang Merah Indonesia No. 16 A-B-C Samarinda</div>
                        <div>HP. 0812 5758 6688</div>
                        <div>Apoteker : apt. Nurlina Muliani, S.Farm., M. Farm</div>
                        <div>No. SIPA : 500.16.7/100/SIPA/100.26</div>
                        <div>No. SIA : 12440004111020004</div>
                    </td>
                </tr>
            </table>

            <div style="border-bottom:2px solid #000; margin-top:8px;"></div>

            {{-- NO & KEPADA --}}
            <table style="width:100%; margin-top:12px;">
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
            <div style="text-align:center; font-weight:bold; font-size:18px; text-decoration:underline; margin-top:15px; margin-bottom:10px;">
                SURAT PESANAN
            </div>

            <div style="margin-bottom:8px;">Mohon dikirim obat-obatan untuk keperluan apotek :</div>

            {{-- TABLE --}}
            <table class="items" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="border:1px solid #000; padding:6px; width:15%;">Jumlah</th>
                        <th style="border:1px solid #000; padding:6px; width:55%;">Nama Obat</th>
                        <th style="border:1px solid #000; padding:6px; width:30%;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $row)
                        <tr>
                            <td style="border:1px solid #000; padding:5px 6px; text-align:center;">
                                {{ optional($row->receiving_items)->qty_received ?? 0 }}
                            </td>
                            <td style="border:1px solid #000; padding:5px 6px;">
                                {{ $row->medicines->name ?? '-' }}
                            </td>
                            <td style="border:1px solid #000; padding:5px 6px;">
                                {{-- optional --}}
                            </td>
                        </tr>
                    @endforeach

                    {{-- Fill empty rows so table always looks full --}}
                    @for ($i = count($items); $i < 12; $i++)
                        <tr>
                            <td style="border:1px solid #000; padding:5px 6px;">&nbsp;</td>
                            <td style="border:1px solid #000; padding:5px 6px;"></td>
                            <td style="border:1px solid #000; padding:5px 6px;"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            {{-- FOOTER --}}
            <table class="footer" style="width:100%; margin-top:15px; page-break-inside: avoid;">
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
                            <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:150px; width:auto;">
                        </div>
                        <b><u>apt. Nurlina Muliani, S.Farm., M. Farm</u></b><br>
                        <span style="font-size:12px;">SIPA : 500.16.7/100/SIPA/100.26</span>
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
        <style>
            .prekursor table.id-table td { padding: 3px 0; vertical-align: top; }
            .prekursor table.form-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .prekursor table.form-table th,
            .prekursor table.form-table td { border: 1px solid #000; padding: 5px 6px; text-align: center; }
            .prekursor table.form-table th { background-color: #f2f2f2; font-weight: bold; }
        </style>

        <div class="prekursor">
            @foreach ($items as $creditorCode => $items)
                <div style="text-align:center; text-decoration:underline; font-weight:bold; font-size:16px;">
                    SURAT PESANAN OBAT MENGANDUNG PREKURSOR FARMASI
                </div>
                <div style="text-align:center; margin-top:4px;">
                    Nomor SP : SP-00{{ $items->first()->id }}
                </div>

                <div style="margin-top:15px; margin-bottom:5px;">Yang bertanda tangan dibawah ini :</div>

                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Apoteker</td>
                        <td style="width:10px;">:</td>
                        <td><b>apt. Nurlina Muliani, S. Farm., M. Farm.</b></td>
                    </tr>
                    <tr>
                        <td>Jabatan</td>
                        <td>:</td>
                        <td>Apoteker Pengelola Apotek</td>
                    </tr>
                    <tr>
                        <td>No. SIPA</td>
                        <td>:</td>
                        <td>500.16.7/100/SIPA/100.26</td>
                    </tr>
                </table>

                <div style="margin-top:12px; margin-bottom:5px;">
                    Mengajukan pesanan obat mengandung Prekursor Farmasi kepada :
                </div>

                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama PBF</td>
                        <td style="width:10px;">:</td>
                        <td><b>{{ optional($items->first()->creditors)->name ?? '-' }}</b></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td>{{ optional($items->first()->creditors)->address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>No. Telp.</td>
                        <td>:</td>
                        <td>{{ optional($items->first()->creditors)->phone ?? '-' }}</td>
                    </tr>
                </table>

                <div style="margin-top:12px; margin-bottom:5px;">
                    Jenis obat mengandung Prekursor Farmasi yang dipesan adalah :
                </div>

                <table class="form-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">No.</th>
                            <th style="width:30%;">Nama Obat Prekursor</th>
                            <th style="width:20%;">Zat Aktif Prekursor</th>
                            <th style="width:15%;">Satuan</th>
                            <th style="width:15%;">Jumlah</th>
                            <th style="width:15%;">Ket.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                <td>{{ $row->medicines->active_substance ?? '-' }}</td>
                                <td>{{ $row->medicines->unit ?? '-' }}</td>
                                <td>{{ optional($row->receiving_items)->qty_received ?? 0 }}</td>
                                <td></td>
                            </tr>
                        @endforeach

                        @for ($i = count($items); $i < 5; $i++)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                <div style="margin-top:12px; margin-bottom:5px;">
                    Obat mengandung Prekursor Farmasi tersebut akan digunakan untuk memenuhi kebutuhan :
                </div>

                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Apotek</td>
                        <td style="width:10px;">:</td>
                        <td><b>Apotek Sahabat</b></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td>Jl. Palang Merah Indonesia No. 16 A-B-C Samarinda</td>
                    </tr>
                    <tr>
                        <td>SIA</td>
                        <td>:</td>
                        <td>12440004111020004</td>
                    </tr>
                </table>

                <table style="width:100%; margin-top:20px; page-break-inside: avoid;">
                    <tr>
                        <td style="width:50%;"></td>
                        <td style="width:50%; text-align:right;">
                            Samarinda, {{ $date }}
                            <br>
                            Pemesan,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:150px; width:auto;">
                            </div>
                            <b><u>apt. Nurlina Muliani, S. Farm., M. Farm.</u></b><br>
                            <span style="font-size:12px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        </div>

    {{-- =========================================================
         3) OBAT-OBAT TERTENTU (OOT)
    ========================================================== --}}
    @elseif ($type == 'OBAT-OBAT TERTENTU (OOT)')
        @foreach ($items as $creditorCode => $items)
            <div style="text-align:center; font-weight:bold; font-size:16px; text-decoration:underline;">
                SURAT PESANAN OBAT-OBAT TERTENTU
            </div>

            <div style="text-align:center; margin-top:4px;">
                Nomor : SP-00{{ $items->first()->id }}
            </div>

            <div style="margin-top:15px; margin-bottom:8px;">Yang bertanda tangan dibawah ini :</div>

            <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
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

            <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
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
                @foreach ($items as $index => $row)
                    <tr>
                        <td style="width:30px; padding:4px 0;">{{ $index + 1 }})</td>
                        <td style="border-bottom:1px dotted #000; padding:4px 0;">
                            <b>{{ $row->medicines->name ?? '-' }}</b>
                            ({{ optional($row->receiving_items)->qty_received ?? 0 }})
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

            <div style="margin-top:12px; margin-bottom:8px;">Obat-Obat Tertentu tersebut akan dipergunakan untuk :</div>

            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="width:130px; vertical-align:top; padding:3px 0;">Nama Sarana</td>
                    <td style="width:10px; vertical-align:top; padding:3px 0;">:</td>
                    <td style="padding:3px 0;">
                        <b>Apotek Sahabat</b><br>
                        <span style="font-size:11px; color:#555;">( PBF / Apotek / Instalasi Farmasi Rumah Sakit / Instalasi Farmasi Klinik )</span>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align:top; padding:3px 0;">Alamat Sarana</td>
                    <td style="vertical-align:top; padding:3px 0;">:</td>
                    <td style="padding:3px 0;">Jl. Palang Merah Indonesia No. 16 A-B-C Samarinda</td>
                </tr>
            </table>

            <table style="width:100%; margin-top:20px; page-break-inside: avoid;">
                <tr>
                    <td style="width:50%;"></td>
                    <td style="width:50%; text-align:right;">
                        Samarinda, {{ $date }}
                        <br>
                        Pemesan,
                        <div style="margin: 4px 0;">
                            <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:150px; width:auto;">
                        </div>
                        <b>( apt. Nurlina Muliani, S.Farm., M.Farm )</b><br>
                        <span style="font-size:12px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                    </td>
                </tr>
            </table>

            <div style="font-size:11px; margin-top:25px; border-top:1px dashed #ccc; padding-top:5px;">
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
        <style>
            .narkotika table.id-table td { padding: 3px 0; vertical-align: top; }
        </style>

        <div class="narkotika">
            @foreach ($items as $creditorCode => $items)
                <div style="text-align:center; text-decoration:underline; font-weight:bold; font-size:16px;">
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
                        <td>Alamat</td>
                        <td>:</td>
                        <td>Jl. Rukun RT. 13 Samarinda</td>
                    </tr>
                    <tr>
                        <td>Jabatan</td>
                        <td>:</td>
                        <td>Apoteker Pengelola Apotek</td>
                    </tr>
                </table>

                <div style="margin-top:12px; margin-bottom:5px;">Mengajukan permohonan kepada :</div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Perusahaan</td>
                        <td style="width:10px;">:</td>
                        <td><b>{{ optional($items->first()->creditors)->name ?? '-' }}</b></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td>{{ optional($items->first()->creditors)->address ?? '-' }}</td>
                    </tr>
                </table>

                {{-- ITEMS --}}
                <div style="margin-top:12px; margin-bottom:5px;">Jenis Narkotika sebagai berikut :</div>
                <div style="padding-left:15px;">
                    @foreach ($items as $index => $row)
                        <div>{{ $index + 1 }}. <b>{{ $row->medicines->name ?? '-' }}</b> ({{ optional($row->receiving_items)->qty_received ?? 0 }})</div>
                    @endforeach
                </div>

                {{-- SARANA --}}
                <div style="margin-top:12px; margin-bottom:5px; font-size:12px; color:#333;">
                    Untuk keperluan Pedagang Besar Farmasi/Apotek/Rumah Sakit/Sarana Penyimpanan Sediaan Farmasi Pemerintah/Lembaga Penelitian dan/atau Lembaga Pendidikan*)
                </div>
                <table class="id-table" style="width:100%;">
                    <tr>
                        <td style="width:140px;">Nama Perusahaan</td>
                        <td style="width:10px;">:</td>
                        <td><b>Apotek Sahabat</b></td>
                    </tr>
                    <tr>
                        <td>Alamat Sarana</td>
                        <td>:</td>
                        <td>Jl. Palang Merah Indonesia No. 16 A-B-C Samarinda</td>
                    </tr>
                    <tr>
                        <td>SIA</td>
                        <td>:</td>
                        <td>12440004111020004</td>
                    </tr>
                </table>

                {{-- SIGNATURE --}}
                <table style="width:100%; margin-top:20px; page-break-inside: avoid;">
                    <tr>
                        <td style="width:50%;"></td>
                        <td style="width:50%; text-align:center;">
                            Samarinda, {{ $date }}
                            <br>
                            Pemesan,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:150px; width:auto;">
                            </div>
                            <b><u>apt. Nurlina Muliani, S. Farm., M. Farm.</u></b><br>
                            <span style="font-size:12px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        </div>

    {{-- =========================================================
         5) PSIKOTROPIKA
    ========================================================== --}}
    @elseif ($type == 'PSIKOTROPIKA')
        <style>
            .psikotropika table.id-table td { padding: 3px 0; vertical-align: top; }
            .psikotropika table.form-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .psikotropika table.form-table th,
            .psikotropika table.form-table td { border: 1px solid #000; padding: 5px 6px; text-align: center; }
            .psikotropika table.form-table th { background-color: #f2f2f2; font-weight: bold; }
        </style>

        <div class="psikotropika">
            @foreach ($items as $creditorCode => $items)
                <div style="text-align:center; text-decoration:underline; font-weight:bold; font-size:16px;">
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
                                <td>{{ $index + 1 }}</td>
                                <td style="text-align:left;">{{ $row->medicines->name ?? '-' }}</td>
                                <td>{{ $row->medicines->form ?? '-' }}</td>
                                <td>{{ $row->medicines->strength ?? '-' }}</td>
                                <td>{{ optional($row->receiving_items)->qty_received ?? 0 }}</td>
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
                        <td>Jl. Palang Merah Indonesia No. 16 A-B-C Samarinda</td>
                    </tr>
                    <tr>
                        <td>No. SIA</td>
                        <td>:</td>
                        <td>12440004111020004</td>
                    </tr>
                </table>

                {{-- SIGNATURE --}}
                <table style="width:100%; margin-top:20px; page-break-inside: avoid;">
                    <tr>
                        <td style="width:50%;"></td>
                        <td style="width:50%; text-align:center;">
                            Samarinda, {{ $date }}
                            <br>
                            Pemesan,
                            <div style="margin: 4px 0;">
                                <img src="{{ public_path('img/ttd-nurlina.png') }}" style="height:150px; width:auto;">
                            </div>
                            <b><u>apt. Nurlina Muliani, S. Farm., M. Farm</u></b><br>
                            <span style="font-size:12px;">SIPA : 500.16.7/100/SIPA/100.26</span>
                        </td>
                    </tr>
                </table>

                {{-- NOTES --}}
                <div style="font-size:11px; margin-top:20px; border-top:1px dashed #ccc; padding-top:5px;">
                    <b>Catatan :</b> <br>
                    - Satu Surat pesanan hanya berlaku untuk satu jenis Psikotropika. <br>
                    - Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.
                </div>

                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        </div>
    @endif
@endforeach