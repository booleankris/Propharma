@extends('layouts.app')

@section('title', 'Export Medicines')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 10V4a1 1 0 0 0-1-1H9.914a1 1 0 0 0-.707.293L5.293 7.207A1 1 0 0 0 5 7.914V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2M10 3v4a1 1 0 0 1-1 1H5m5 6h9m0 0-2-2m2 2-2 2" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800">Export Data Obat</h2>
                    </div>

                    <!-- EXPORT BUTTON -->
                    <button id="exportMedicinesBtn"
                        class="w-full py-3 rounded-lg text-white font-semibold bg-blue-600 hover:bg-blue-700 transition">
                        Export Obat
                    </button>

                    <!-- PROGRESS BAR -->
                    <div id="medicineProgressContainer" class="mt-5 hidden">
                        <p class="font-semibold text-gray-700 mb-1">Export Progress</p>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div id="medicineProgressBar" class="h-4 bg-green-500 rounded-full" style="width: 0%"></div>
                        </div>
                        <p id="medicineProgressText" class="text-sm mt-1 text-gray-600">0%</p>
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script>
        const exportMedicinesBtn = document.getElementById("exportMedicinesBtn");
        const medicineProgressContainer = document.getElementById("medicineProgressContainer");
        const medicineProgressBar = document.getElementById("medicineProgressBar");
        const medicineProgressText = document.getElementById("medicineProgressText");

        exportMedicinesBtn.addEventListener("click", function() {
            fetch("/reports/export/medicines", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(res => res.json())
                .then(data => {
                    const jobId = data.job_id;

                    // Show progress bar
                    medicineProgressContainer.classList.remove("hidden");

                    pollMedicineProgress(jobId);
                });
        });

        function pollMedicineProgress(jobId) {
            const interval = setInterval(() => {
                fetch(`/reports/export/medicines/status/${jobId}`)
                    .then(res => res.json())
                    .then(job => {

                        // Update progress
                        medicineProgressBar.style.width = job.progress + "%";
                        medicineProgressText.textContent = job.progress + "%";

                        // Completed
                        if (job.status === "completed") {
                            clearInterval(interval);
                            medicineProgressText.textContent = "Selesai!";
                            window.location.href = `/reports/export/medicines/download/${jobId}`;
                        }

                        // Failed
                        if (job.status === "failed") {
                            clearInterval(interval);
                            medicineProgressText.textContent = "Gagal!";
                            medicineProgressBar.style.background = "red";
                        }
                    });
            }, 1000);
        }
    </script>
@endsection
