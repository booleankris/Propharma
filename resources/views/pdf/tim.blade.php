<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Tim {{ $team->name }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }

        h2, h3 {
            margin-bottom: 5px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            border-bottom: 2px solid #000;
            margin-bottom: 10px;
            padding-bottom: 3px;
        }

        .profile-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .profile-card {
            display: table;
            /* width: 100%; */
            border: 1px solid #ccc;
            padding: 10px;
            page-break-inside: avoid;
        }

        .profile-row {
            display: table-row;
        }

        .profile-image-cell,
        .profile-info-cell {
            display: table-cell;
            vertical-align: top;
            padding-right: 10px;
        }

        .profile-image-cell {
            width: 200px;
        }

        .profile-image-cell img {
            width: 200px;
            height: auto;
            object-fit: cover;
            border: 1px solid #999;
            display: block;
            margin-bottom: 5px;
        }

        .ktp-image {
            width: 200px;
            height: auto;
            object-fit: cover;
        }

        .profile-info-cell p {
            margin: 2px 0;
        }
    </style>
</head>
<body>
    <!-- Informasi Tim -->
    <div class="section">
        <h2 class="section-title">Data Tim: {{ $team->name }}</h2>

        <div style="display: flex; gap: 16px;">
            @if ($team->logo)
                <div>
                    {{-- <img src="{{ public_path('uploads/teams/'. $team->logo) }}" alt="Logo {{ $team->name }}" style="max-height: 80px; border: 1px solid #999;"> --}}
                    <img src="{{ $team->logo_url }}" alt="Logo {{ $team->name }}" style="max-height: 80px; border: 1px solid #999;">
                </div>
            @endif
            <div>
                <p><strong>Penanggung Jawab:</strong> {{ $team->manager_name }}</p>
                <p><strong>Kontak:</strong> {{ $team->manager_phone ?? '-' }}</p>
                <p><strong>Email:</strong> {{ $team->manager_email ?? '-' }}</p>
            </div>
        </div>
    </div>

    <!-- Data Official -->
    <div class="section">
        <h3 class="section-title">Daftar Official</h3>
        <div class="profile-list">
            @foreach($officials as $official)
                <div class="profile-card">
                    <div class="profile-row">
                        <div class="profile-image-cell">
                            {{-- <img src="{{ public_path('uploads/official/' . $official->photo) }}" alt="Foto {{ $official->name }}"> --}}
                            <img src="{{ $official->image_url }}" alt="Foto {{ $official->name }}">
                        </div>
                        <div class="profile-info-cell">
                            <p><strong>Nama:</strong> {{ $official->name }}</p>
                            <p><strong>Keterangan:</strong> {{ $official->details }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Data Pemain -->
    <div class="section">
        <h3 class="section-title">Daftar Pemain</h3>
        <div class="profile-list">
            @foreach($players as $player)
                <div class="profile-card">
                    <div class="profile-row">
                        <div class="profile-image-cell">
                            {{-- <img src="{{ public_path('uploads/squad/' . $player->photo) }}" alt="Foto {{ $player->name }}"> --}}
                            <img src="{{ $player->image_url }}" alt="Foto {{ $player->name }}">


                            @if($player->identity_card)
                                {{-- <img src="{{ public_path('uploads/squad/identitycard/' . $player->identity_card) }}" alt="KTP {{ $player->name }}" class="ktp-image"> --}}
                                <img src="{{  $player->identity_url }}" alt="KTP {{ $player->name }}" class="ktp-image">
                            @endif
                        </div>
                        <div class="profile-info-cell">
                            <p><strong>Nama:</strong> {{ $player->name }}</p>
                            <p><strong>No. Punggung:</strong> {{ $player->number }}</p>
                            <p><strong>Posisi:</strong> {{ $player->position }}</p>
                            <p><strong>Tanggal Lahir:</strong> {{ \Carbon\Carbon::parse($player->dateofbirth)->translatedFormat('d F Y') }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <script>
        // Auto print ketika halaman dimuat (opsional)
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
