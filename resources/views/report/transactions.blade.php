@extends('layouts.app')

@section('title', 'Patients')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">

                        <svg class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 10V4a1 1 0 0 0-1-1H9.914a1 1 0 0 0-.707.293L5.293 7.207A1 1 0 0 0 5 7.914V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2M10 3v4a1 1 0 0 1-1 1H5m5 6h9m0 0-2-2m2 2-2 2" />
                        </svg>

                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Export Data Transaksi</h2>
                    </div>

                    <div class="flex gap-4 items-end flex-wrap">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Start Date</label>
                            <input type="date" id="start_date" class="border rounded-lg px-3 py-2 text-sm w-40">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">End Date</label>
                            <input type="date" id="end_date" class="border rounded-lg px-3 py-2 text-sm w-40">
                        </div>

                        <div class="flex gap-2">
                            <button id="exportBtn"
                                class="w-full py-3  px-3 rounded-lg text-white font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed bg-blue-600"
                                style="background: #1A73E8;">
                                Export Semua
                            </button>
                            <div onclick="back()"
                                class="px-5 py-3 cursor-pointer bg-red-600 text-white rounded-lg shadow transition-all duration-200">
                                Kembali
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div id="progressContainer" class="mt-5 hidden">
                        <p class="font-semibold text-gray-700 mb-1">Export Progress</p>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div id="progressBar" class="h-4 bg-green-500 rounded-full" style="width: 0%;"></div>
                        </div>
                        <p id="progressText" class="text-sm mt-1 text-gray-600">0%</p>
                    </div>

                </div>
            </div>
        </div>
    </section>
@section('scripts')
    <script>
        function back() {
            window.location.href = "{{ route('home') }}";
        }
        const startInput = document.getElementById("start_date");
        const endInput = document.getElementById("end_date");
        const exportBtn = document.getElementById("exportBtn");

        const progressContainer = document.getElementById("progressContainer");
        const progressBar = document.getElementById("progressBar");
        const progressText = document.getElementById("progressText");

        // Validate on load
        updateExportButton();

        // Monitor input changes
        startInput.addEventListener("input", updateExportButton);
        endInput.addEventListener("input", updateExportButton);

        function updateExportButton() {
            const start = startInput.value;
            const end = endInput.value;

            // No dates → Export Semua
            if (!start && !end) {
                exportBtn.textContent = "Export Semua";
                exportBtn.disabled = false;
                exportBtn.style.background = "#1A73E8";
                return;
            }

            // Only one date → disabled
            if ((start && !end) || (!start && end)) {
                exportBtn.textContent = "Export";
                exportBtn.disabled = true;
                exportBtn.style.background = "#9CA3AF"; // disabled gray
                return;
            }

            // Both dates filled → enabled Export
            exportBtn.textContent = "Export";
            exportBtn.disabled = false;
            exportBtn.style.background = "#1A73E8";
        }

        // Export button click
        exportBtn.addEventListener("click", function() {

            const start = startInput.value;
            const end = endInput.value;

            fetch("/reports/export/transactions", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        start: start,
                        end: end
                    })
                })
                .then(res => res.json())
                .then(data => {
                    const jobId = data.job_id;

                    progressContainer.classList.remove("hidden");

                    pollProgress(jobId);
                });
        });


        function pollProgress(jobId) {
            const interval = setInterval(() => {
                fetch(`/reports/export/transactions/status/${jobId}`)
                    .then(res => res.json())
                    .then(job => {

                        progressBar.style.width = job.progress + "%";
                        progressText.textContent = job.progress + "%";

                        if (job.status === "completed") {
                            clearInterval(interval);
                            progressText.textContent = "Selesai!";

                            // Auto download
                            window.location.href = `/reports/export/transactions/download/${jobId}`;
                        }

                        if (job.status === "failed") {
                            clearInterval(interval);
                            progressText.textContent = "Gagal!";
                            progressBar.style.background = "red";
                        }
                    });
            }, 1000);
        }
    </script>

@endsection
@endsection
