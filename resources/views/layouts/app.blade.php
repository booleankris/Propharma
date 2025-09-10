<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="/src/style.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <title>MyPOS | Dashboard</title>
</head>

<body class="dashboard-body">
    @include('components.header')
    <main class="my-[95px] pb-[80px]">
        @yield('content')
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        let quantitySubmitting = false;

        function showLoading(event) {
            if (quantitySubmitting) {
                event.preventDefault();
                return false;
            }

            quantitySubmitting = true;
            const form = event.target;

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
                btn.disabled = true;
            });

            Swal.fire({
                title: 'Mohon Tunggu...',
                didOpen: () => {
                    Swal.showLoading();
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            return true;
        }
        let cartSubmitting = false;

        function showLoadingCart(event) {
            if (cartSubmitting) {
                event.preventDefault();
                return false;
            }

            cartSubmitting = true;
            const form = event.target;

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
                btn.disabled = true;
            });

            Swal.fire({
                title: 'Mohon Tunggu...',
                didOpen: () => {
                    Swal.showLoading();
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            return true;
        }

        function filterItems() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const cards = document.querySelectorAll('#items-container .item-card');

            cards.forEach(card => {
                const itemName = card.querySelector('.item-name')?.textContent.toLowerCase();
                if (!itemName) return; // skip if no name found (safety)
                card.style.display = itemName.includes(filter) || filter === '' ? '' : 'none';
            });
        }

        // Attach once DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', filterItems);
            }
        });
    </script>

    @if (session()->has('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: @json(session('success')),
                confirmButtonText: 'OK',
                buttonsStyling: false, // disable SweetAlert default button styling
                customClass: {
                    confirmButton: 'bg-[#3085d6] text-white px-4 py-2 rounded hover:bg-[#2563eb]',
                    title: 'swal-title',
                    popup: 'swal-popup',
                    content: 'swal-text',
                }
            });
        </script>
    @endif
    @if (session()->has('failed'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: @json(session('failed')),
                confirmButtonText: 'OK',
                buttonsStyling: false, // disable SweetAlert default button styling
                customClass: {
                    confirmButton: 'bg-[#3085d6] text-white px-4 py-2 rounded hover:bg-[#2563eb]',
                    title: 'swal-title',
                    popup: 'swal-popup',
                    content: 'swal-text',
                }
            });
        </script>
    @endif
    @if (session()->has('loading'))
        <script>
            Swal.fire({
                title: 'Mohon tunggu...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        </script>
    @endif

    <script>
        $(document).ready(function() {

            // JS
            new Swiper('.productSwiper', {
                slidesPerView: 2,
                spaceBetween: 8,
                grabCursor: true,
                loop: true,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    640: {
                        slidesPerView: 2,
                    },
                    768: {
                        slidesPerView: 2,
                    },
                    1024: {
                        slidesPerView: 3,
                    },
                },
            });

        });
    </script>
    <script>
        // const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

        // const imageInput = document.getElementById('imageInput');
        // const previewContainer = document.getElementById('previewContainer');
        // const previewImage = document.getElementById('previewImage');
        // const removeButton = document.getElementById('removeImage');

        // Handle image preview
        // imageInput.addEventListener('change', function(event) {
        //     const file = event.target.files[0];
        //     if (file) {
        //         if (file.size > MAX_IMAGE_SIZE) {
        //             alert("Gambar Tidak Boleh Lebih Dari 5MB.");
        //             imageInput.value = ""; // Clear file input
        //             previewImage.src = "";
        //             previewContainer.classList.add('hidden');
        //             return;
        //         }

        //         const reader = new FileReader();
        //         reader.onload = function(e) {
        //             previewImage.src = e.target.result;
        //             previewContainer.classList.remove('hidden');
        //         };
        //         reader.readAsDataURL(file);
        //     }
        // });

        // // Handle remove button
        // removeButton.addEventListener('click', function() {
        //     previewImage.src = "";
        //     previewContainer.classList.add('hidden');
        //     imageInput.value = ""; // Clear file input
        // });
    </script>
    @include('components.footer')

</html>
