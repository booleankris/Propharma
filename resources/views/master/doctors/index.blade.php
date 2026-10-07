@extends('layouts.app')

@section('title', 'Dokter')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">

            <div class="flex flex-col lg:flex-row gap-4">

                {{-- LEFT: TABLE --}}
                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 11c1.657 0 3-1.343 3-3S17.657 5 16 5s-3 1.343-3 3 1.343 3 3 3zM8 11c1.657 0 3-1.343 3-3S9.657 5 8 5 5 6.343 5 8s1.343 3 3 3zm0 2c-2.5 0-4.5 1.5-4.5 3.5V19h9v-2.5C12.5 14.5 10.5 13 8 13zm8 0c-2.5 0-4.5 1.5-4.5 3.5V19h9v-2.5c0-2-2-3.5-4.5-3.5z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 tracking-wide drop-shadow-sm">Data Dokter</h2>
                        @include('master.partials.export-button', ['type' => 'doctors'])
                        <button type="button" id="btnOpenMergeModal" class="ml-2 px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white font-medium text-xs rounded-lg shadow transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Gabung Duplikat
                        </button>
                        <div class="w-48 ml-3">
                            <select id="filter_pharmacy" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs focus:ring-2 focus:ring-blue-200">
                                <option value="">Semua Pharmacy</option>
                                @foreach ($pharmacies as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Pharmacy</th>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Specialist</th>
                                    <th class="px-4 py-3">Address</th>
                                    <th class="px-4 py-3">City</th>
                                    <th class="px-4 py-3">Phone</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                {{-- RIGHT: FORM --}}
                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[35%] mx-auto">
                    <form id="doctorForm" action="{{ route('doctors.store') }}" method="POST" class="space-y-2">
                        @csrf

                        <input type="hidden" id="doctor_id" name="id">

                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Pharmacy</label>
                            <select id="pharmacy_id" name="pharmacy_id"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm">
                                <option value="">-- Select Pharmacy --</option>
                                @foreach ($pharmacies as $pharmacy)
                                    <option value="{{ $pharmacy->id }}">{{ $pharmacy->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">Code</label>
                                <input id="code" name="code" readonly
                                    class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">Name</label>
                                <input id="name" name="name"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                    placeholder="Enter name">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Specialist</label>
                            <input id="specialist" name="specialist"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                placeholder="Enter specialist">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Address</label>
                            <input id="address" name="address"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                placeholder="Enter address">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">City</label>
                                <input id="city" name="city"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                    placeholder="Enter city">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">Phone</label>
                                <input id="phone" name="phone"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                    placeholder="Enter phone">
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-3">
                            <div>
                                <button type="button" id="submitForm"
                                    class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow">
                                    Submit
                                </button>
                            </div>
                            <div>
                                <button type="button" id="cancelEdit"
                                    class="px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg shadow">
                                    Cancel
                                </button>
                            </div>
                            <div>
                                <button type="button" id="deleteData"
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg shadow">
                                    Delete
                                </button>
                            </div>
                            <div>
                                <button type="button" id="back"
                                    class="px-5 py-3 w-full bg-[#FF9800] hover:bg-[#FF9232] text-white rounded-lg shadow-lg hover:shadow-red-400/70 transition-all duration-300">
                                    Kembali
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </section>

    {{-- MODAL MERGE DUPLIKAT DOKTER --}}
    <div id="mergeModal" class="fixed inset-0 bg-black/50 hidden justify-center items-center z-[99999]">
        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl p-6 relative max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="p-2 bg-amber-100 text-amber-600 rounded-lg mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Merge Data Duplikat Dokter</h3>
                        <p class="text-xs text-gray-500">Pindai dan gabungkan data dokter yang terduplikasi (beda spasi, tanda baca, gelar, atau typo).</p>
                    </div>
                </div>
                <button type="button" id="closeMergeModal" class="text-gray-400 hover:text-gray-600 text-2xl font-bold px-2">&times;</button>
            </div>

            <div id="mergeLoading" class="py-12 text-center text-gray-500">
                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memindai duplikat dokter...
            </div>

            <div id="mergeContent" class="overflow-y-auto flex-1 my-4 space-y-4 pr-1 hidden"></div>

            <div class="pt-4 border-t border-gray-200 flex justify-between items-center">
                <span id="mergeSummaryText" class="text-xs font-semibold text-gray-600"></span>
                <div class="flex gap-2">
                    <button type="button" id="btnRefreshDuplicates" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg">
                        Re-Scan
                    </button>
                    <button type="button" id="btnMergeAllExact" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg shadow hidden">
                        Gabung Semua Exact
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        let tableData, selectedData = null;
        const form = document.getElementById('doctorForm');

        $(function() {

            $('#pharmacy_id').select2({
                placeholder: '-- Select Pharmacy --',
                width: '100%'
            });

            tableData = $('#table-data').DataTable({
                responsive: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('doctors.index') }}',
                    data: function(d) {
                        d.pharmacy_id = $('#filter_pharmacy').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'pharmacy_name',
                        name: 'pharmacies.name'
                    },
                    {
                        data: 'code',
                        name: 'doctors.code'
                    },
                    {
                        data: 'name',
                        name: 'doctors.name'
                    },
                    {
                        data: 'specialist',
                        name: 'doctors.specialist'
                    },
                    {
                        data: 'address',
                        name: 'doctors.address'
                    },
                    {
                        data: 'city',
                        name: 'doctors.city'
                    },
                    {
                        data: 'phone',
                        name: 'doctors.phone'
                    },
                ],
            });

            $('#filter_pharmacy').on('change', function() {
                tableData.ajax.reload();
            });

            // Row select
            $('#table-data tbody').on('click', 'tr', function() {
                selectedData = tableData.row(this).data();
                if (!selectedData) return;

                $('#table-data tbody tr').removeClass('bg-blue-100');
                $(this).addClass('bg-blue-100');

                $('#doctor_id').val(selectedData.id);
                $('#pharmacy_id').val(selectedData.pharmacy_id).trigger('change');

                $('#code').val(selectedData.code);
                $('#name').val(selectedData.name);
                $('#specialist').val(selectedData.specialist);
                $('#address').val(selectedData.address);
                $('#city').val(selectedData.city);
                $('#phone').val(selectedData.phone);
            });

            $('#cancelEdit').on('click', function() {
                form.reset();
                $('#pharmacy_id').val('').trigger('change');
                $('#doctor_id').val('');
                $('#table-data tbody tr').removeClass('bg-blue-100');
                selectedData = null;
            });
            // BACK
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });
            // DELETE
            $('#deleteData').on('click', function() {
                const id = $('#doctor_id').val();
                if (!id)
                    return iziToast.warning({
                        title: 'Warning',
                        message: 'No data selected!',
                        position: 'topRight'
                    });

                axios.delete('/doctors/' + id)
                    .then(res => {
                        iziToast.success({
                            title: 'Deleted',
                            message: res.data.message,
                            position: 'topRight'
                        });
                        $('#cancelEdit').click();
                        tableData.ajax.reload();
                    });
            });
        });
        // === ENTER Key Navigation for Doctors ===

        // Order of fields in the Doctors form
        const fieldOrder = [
            "pharmacy_id", // select2
            "code",
            "name",
            "specialist",
            "address",
            "city",
            "phone"
        ];

        function getField(id) {
            return document.getElementById(id);
        }

        const fields = fieldOrder.map(id => getField(id));

        fields.forEach((field, index) => {
            // Select2 requires special event handling
            if (field && field.tagName === "SELECT") {

                $(field).on("select2:close", function() {
                    const nextField = fields[index + 1];
                    if (nextField) nextField.focus();
                });

            } else if (field) {

                field.addEventListener("keydown", e => {
                    if (e.key === "Enter") {
                        e.preventDefault();

                        const nextField = fields[index + 1];

                        if (nextField) {
                            nextField.focus();
                        } else {
                            handleSubmit(); // last field
                        }
                    }
                });
            }
        });



        document.getElementById('submitForm').addEventListener('click', handleSubmit);

        function handleSubmit() {
            const id = $('#doctor_id').val();
            const formData = new FormData(form);
            const url = id ? `/doctors/${id}` : form.action;

            if (id) formData.append('_method', 'PUT');

            axios.post(url, formData)
                .then(res => {
                    iziToast.success({
                        title: 'Success',
                        message: res.data.message,
                        position: 'topRight'
                    });
                    $('#cancelEdit').click();
                    tableData.ajax.reload();
                })
                .catch(err => {
                    let msg = 'Failed to save.';
                    if (err.response?.status === 422) {
                        msg = Object.values(err.response.data.errors).flat().join('<br>');
                    }
                    iziToast.error({
                        title: 'Error',
                        message: msg,
                        position: 'topRight'
                    });
                });
        }

        // ==================== MERGE DUPLIKAT DOKTER ====================
        let currentDuplicateGroups = [];

        $('#btnOpenMergeModal').on('click', function() {
            $('#mergeModal').removeClass('hidden').addClass('flex');
            loadDuplicates();
        });

        $('#closeMergeModal').on('click', function() {
            $('#mergeModal').addClass('hidden').removeClass('flex');
        });

        $('#btnRefreshDuplicates').on('click', function() {
            loadDuplicates();
        });

        function loadDuplicates() {
            $('#mergeLoading').removeClass('hidden');
            $('#mergeContent').addClass('hidden').empty();
            $('#btnMergeAllExact').addClass('hidden');
            $('#mergeSummaryText').text('');

            axios.get('{{ route("doctors.duplicates") }}')
                .then(res => {
                    $('#mergeLoading').addClass('hidden');
                    $('#mergeContent').removeClass('hidden');

                    if (!res.data.groups || res.data.groups.length === 0) {
                        $('#mergeContent').html(`
                            <div class="p-8 text-center bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200">
                                <svg class="w-10 h-10 mx-auto mb-2 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="font-bold text-base">Tidak Ada Duplikat Ditemukan</div>
                                <div class="text-xs text-emerald-600 mt-1">Semua data dokter sudah unik dan bersih!</div>
                            </div>
                        `);
                        return;
                    }

                    currentDuplicateGroups = res.data.groups;
                    let exactCount = 0, fuzzyCount = 0;

                    res.data.groups.forEach((group, gIdx) => {
                        if (group.type === 'EXACT') exactCount++;
                        else fuzzyCount++;

                        const canonical = group.canonical;
                        const duplicates = group.members.filter(m => m.id !== canonical.id);
                        const dupIds = duplicates.map(d => d.id);

                        let badgeColor = group.type === 'EXACT' 
                            ? 'bg-blue-100 text-blue-800 border-blue-200' 
                            : 'bg-purple-100 text-purple-800 border-purple-200';
                        let badgeText = group.type === 'EXACT' ? 'Sama Persis (EXACT)' : 'Mirip / Typo (FUZZY)';

                        let dupListHtml = duplicates.map(d => `
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-xs border border-gray-100">
                                <div>
                                    <span class="font-medium text-gray-800">${d.name}</span>
                                    <span class="text-gray-400 ml-2">(${d.code})</span>
                                    <div class="text-[11px] text-gray-500">${d.specialist ? d.specialist + ' • ' : ''}${d.city || d.address || 'Tanpa alamat'}</div>
                                </div>
                                <span class="bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full font-semibold text-[10px]">
                                    ${d.tx_count || 0} Transaksi
                                </span>
                            </div>
                        `).join('');

                        let groupCard = `
                            <div class="p-4 rounded-xl border border-gray-200 bg-white shadow-sm hover:shadow transition-all" id="group-card-${gIdx}">
                                <div class="flex items-start justify-between mb-3 pb-2 border-b border-gray-100">
                                    <div>
                                        <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full border ${badgeColor}">
                                            ${badgeText}
                                        </span>
                                        <span class="ml-2 text-xs text-gray-500 font-medium">
                                            ${group.members.length} variasi data
                                        </span>
                                    </div>
                                    <button type="button" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg shadow btn-merge-single"
                                        data-[gidx]="${gIdx}" data-canonical="${canonical.id}" data-dupids='${JSON.stringify(dupIds)}'>
                                        Gabungkan Grup Ini
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-lg">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 mb-1 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Dokter Utama (Penerima)
                                        </div>
                                        <div class="font-bold text-sm text-gray-800">${canonical.name} <span class="text-xs font-normal text-gray-500">(${canonical.code})</span></div>
                                        <div class="text-xs text-gray-600">${canonical.specialist ? canonical.specialist + ' • ' : ''}${canonical.city || canonical.address || 'Tanpa alamat'}</div>
                                        <div class="mt-1 text-[11px] font-semibold text-emerald-700">${canonical.tx_count || 0} Transaksi saat ini</div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                            Duplikat Akan Dihapus (${duplicates.length}) & Transaksinya Dipindahkan
                                        </div>
                                        ${dupListHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#mergeContent').append(groupCard);
                    });

                    $('#mergeSummaryText').text(`Ditemukan ${res.data.groups.length} grup duplikat (${exactCount} exact, ${fuzzyCount} fuzzy).`);

                    if (exactCount > 0) {
                        $('#btnMergeAllExact').removeClass('hidden');
                    }
                })
                .catch(err => {
                    $('#mergeLoading').addClass('hidden');
                    $('#mergeContent').removeClass('hidden').html(`
                        <div class="p-4 bg-red-50 text-red-700 rounded-lg text-xs">
                            Gagal memuat duplikat: ${err.message}
                        </div>
                    `);
                });
        }

        $(document).on('click', '.btn-merge-single', function() {
            const canonicalId = $(this).data('canonical');
            const dupIds = $(this).attr('data-dupids');
            const parsedDupIds = typeof dupIds === 'string' ? JSON.parse(dupIds) : dupIds;

            if (!confirm(`Gabungkan ${parsedDupIds.length} dokter duplikat ke dokter utama ini?`)) return;

            const btn = $(this);
            btn.prop('disabled', true).text('Proses...');

            axios.post('{{ route("doctors.mergeDuplicates") }}', {
                canonical_id: canonicalId,
                duplicate_ids: parsedDupIds
            })
            .then(res => {
                iziToast.success({
                    title: 'Berhasil',
                    message: res.data.message,
                    position: 'topRight'
                });
                tableData.ajax.reload();
                loadDuplicates();
            })
            .catch(err => {
                btn.prop('disabled', false).text('Gabungkan Grup Ini');
                let msg = err.response?.data?.message || 'Gagal menggabungkan.';
                iziToast.error({
                    title: 'Error',
                    message: msg,
                    position: 'topRight'
                });
            });
        });

        $('#btnMergeAllExact').on('click', async function() {
            const exactGroups = currentDuplicateGroups.filter(g => g.type === 'EXACT');
            if (exactGroups.length === 0) return;

            if (!confirm(`Gabungkan SEMUA (${exactGroups.length}) grup duplikat EXACT secara otomatis?`)) return;

            let successCount = 0;
            for (const g of exactGroups) {
                const canonicalId = g.canonical.id;
                const dupIds = g.members.filter(m => m.id !== canonicalId).map(m => m.id);

                try {
                    await axios.post('{{ route("doctors.mergeDuplicates") }}', {
                        canonical_id: canonicalId,
                        duplicate_ids: dupIds
                    });
                    successCount++;
                } catch (e) {
                    console.error('Merge group failed', g, e);
                }
            }

            iziToast.success({
                title: 'Selesai',
                message: `${successCount} dari ${exactGroups.length} grup duplikat exact berhasil digabungkan!`,
                position: 'topRight'
            });

            tableData.ajax.reload();
            loadDuplicates();
        });
    </script>
@endsection
