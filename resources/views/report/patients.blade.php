@extends('layouts.app')

@section('title', 'Patients')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <style>
        #progress-box {
            display: none;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">

            <div class="card w-full md:w-2/3 shadow-md rounded-2xl p-6 bg-white">
                <div class="flex items-center mb-6">
                    <svg class="w-8 h-8 text-blue-600 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 10V4a1 1 0 0 0-1-1H9.914a1 1 0 0 0-.707.293L5.293 7.207A1 1 0 0 0 5 7.914V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2M10 3v4a1 1 0 0 1-1 1H5m5 6h9m0 0-2-2m2 2-2 2" />
                    </svg>
                    <h2 class="text-2xl font-bold text-gray-800">Export Data Pasien</h2>
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
                        <button id="btnExport"
                            class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow transition-all duration-200">
                            Export Semua
                        </button>
                        <div onclick="back()"
                            class="px-5 py-2 cursor-pointer bg-red-600 text-white rounded-lg shadow transition-all duration-200">
                            Kembali
                        </div>
                    </div>
                </div>

                <!-- PROGRESS BAR -->
                <div id="progress-box" class="mt-6">
                    <label class="font-semibold text-gray-700">Progress:</label>
                    <div class="w-full bg-gray-200 rounded-full h-4 mt-1">
                        <div id="progress-bar" class="bg-blue-600 h-4 rounded-full" style="width: 0%"></div>
                    </div>
                    <p id="progress-text" class="mt-2 text-sm font-medium text-gray-600">0%</p>
                </div>

            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        function back() {
            window.location.href = "{{ route('home') }}";
        }
        document.addEventListener("DOMContentLoaded", function() {

            const startInput = document.getElementById("start_date");
            const endInput = document.getElementById("end_date");
            const exportBtn = document.getElementById("btnExport");
            const progressBox = document.getElementById("progress-box");
            const progressBar = document.getElementById("progress-bar");
            const progressText = document.getElementById("progress-text");


            function setDisabled(state) {
                exportBtn.disabled = state;
                exportBtn.style.cursor = state ? "not-allowed" : "pointer";
                exportBtn.style.opacity = state ? "0.6" : "1";
            }

            function updateButton() {
                const start = startInput.value;
                const end = endInput.value;

                if (!start && !end) {
                    exportBtn.textContent = "Export Semua";
                    setDisabled(false);
                } else if (start && end) {
                    exportBtn.textContent = "Export";
                    setDisabled(false);
                } else {
                    exportBtn.textContent = "Export";
                    setDisabled(true);
                }
            }

            startInput.addEventListener("input", updateButton);
            endInput.addEventListener("input", updateButton);
            updateButton();

            // EXPORT CLICK
            exportBtn.addEventListener("click", function() {

                let payload = {
                    start_date: startInput.value,
                    end_date: endInput.value
                };

                setDisabled(true);
                progressBox.style.display = "block";
                progressBar.style.width = "0%";
                progressText.textContent = "0%";

                iziToast.info({
                    title: "Processing",
                    message: "Export is being generated...",
                    position: "topRight"
                });

                fetch("{{ route('reports.export.patients') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(res => res.json())
                    .then(data => {
                        checkStatus(data.job_id);
                    });
            });

            function checkStatus(jobId) {
                let interval = setInterval(() => {
                    fetch(`/reports/export/status/${jobId}`)
                        .then(res => res.json())
                        .then(data => {
                            progressBar.style.width = `${data.progress}%`;
                            progressText.textContent = `${data.progress}%`;

                            if (data.status === "finished") {
                                clearInterval(interval);
                                setDisabled(false);

                                iziToast.success({
                                    title: "Success",
                                    message: "Export completed! Download will start.",
                                    position: "topRight"
                                });

                                window.location.href = data.file;
                            }

                            if (data.status === "failed") {
                                clearInterval(interval);
                                setDisabled(false);

                                iziToast.error({
                                    title: "Failed",
                                    message: "Export process failed!",
                                    position: "topRight"
                                });
                            }

                        });
                }, 1500);
            }

        });
    </script>
@endsection
