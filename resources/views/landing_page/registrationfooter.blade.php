<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0/jquery.min.js"></script>

<!-- jQuery Modal -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css" />

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.tailwindcss.com"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        // =================================================== Checkout Page ======================================================

        const quantityInput = document.getElementById('quantity');
        const maxQty = 4;

        document.getElementById('increase').addEventListener('click', () => {
            let current = parseInt(quantityInput.value);
            if (current < maxQty) {
                quantityInput.value = current + 1;
            }
        });

        document.getElementById('decrease').addEventListener('click', () => {
            let current = parseInt(quantityInput.value);
            if (current > 1) {
                quantityInput.value = current - 1;
            }
        });
        // ========================================================================
        flatpickr("#datepicker", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "F j, Y",
        });

        const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

        const imageInput = document.getElementById('imageInput');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        const removeButton = document.getElementById('removeImage');

        // Handle image preview
        imageInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > MAX_IMAGE_SIZE) {
                    alert("Gambar Tidak Boleh Lebih Dari 5MB.");
                    imageInput.value = ""; // Clear file input
                    previewImage.src = "";
                    previewContainer.classList.add('hidden');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        // Handle remove button
        removeButton.addEventListener('click', function() {
            previewImage.src = "";
            previewContainer.classList.add('hidden');
            imageInput.value = ""; // Clear file input
        });

    });
</script>
@if (session()->has('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'Data Successfully Created!',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK',
            customClass: {
                title: 'swal-title',
                popup: 'swal-popup',
                content: 'swal-text',
            }
        })
    </script>
@endif
@if (session()->has('deletesuccess'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'Data Successfully Deleted!',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'OK',
            customClass: {
                title: 'swal-title',
                popup: 'swal-popup',
                content: 'swal-text',
            }
        })
    </script>
@endif
@if (session()->has('failed'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '{{ session('failed') }}',
            confirmButtonColor: '#d33',
            confirmButtonText: 'OK',
            customClass: {
                title: 'swal-title',
                popup: 'swal-popup',
                content: 'swal-text',
            }
        });
    </script>
@endif

<script>
    AOS.init({
        offset: 120, // offset (in px) from the original trigger point
        delay: 0, // values from 0 to 3000, with step 50ms
        duration: 1000, // values from 0 to 3000, with step 50ms
        easing: 'ease', // default easing for AOS animations
    });
</script>

<script>
    $("#investara").click(function() {
        $("#popupinvestara").fadeIn();
    });

    $("[data-close]").click(function() {
        $(this).parents(".modal").fadeOut();
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('membermodal');
        const modalContent = document.getElementById('modalContent');
        const modalContent2 = document.getElementById('modalContent2');
        const modalofficial = document.getElementById('officialmodal');

        // ✅ Make it globally accessible
        window.toggleModalSquad = function() {
            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            } else {
                modalContent.classList.add('scale-95', 'opacity-0');
                modalContent.classList.remove('scale-100', 'opacity-100');
                setTimeout(() => modal.classList.add('hidden'), 200);
            }
        }

        // ✅ Use correct function name
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                toggleModalSquad();
            }
        });

        // ✅ Make it globally accessible
        window.toggleModalOfficial = function() {
            if (modalofficial.classList.contains('hidden')) {
                modalofficial.classList.remove('hidden');
                setTimeout(() => {
                    modalContent2.classList.remove('scale-95', 'opacity-0');
                    modalContent2.classList.add('scale-100', 'opacity-100');
                }, 10);
            } else {
                modalContent2.classList.add('scale-95', 'opacity-0');
                modalContent2.classList.remove('scale-100', 'opacity-100');
                setTimeout(() => modalofficial.classList.add('hidden'), 200);
            }
        }

        // ✅ Use correct function name
        window.addEventListener('click', function(e) {
            if (e.target === modalofficial) {
                toggleModalOfficial();
            }
        });
    });
</script>


<script>
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

    const imageInput = document.getElementById('MemberFile');
    const dropAreaElement = document.getElementById('drop-area');
    const placeholderElement = document.getElementById('placeholder');
    const previewImage = document.getElementById('previewMember');

    // Trigger file input on click
    dropAreaElement.addEventListener('click', () => {
        imageInput.click();
    });

    function showImagePreview(file) {
        if (file.size > MAX_IMAGE_SIZE) {
            alert('Image size must be under 5MB.');
            imageInput.value = ''; // Clear file input
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewImage.classList.remove('hidden');
            placeholderElement.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }

    imageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            showImagePreview(this.files[0]);
        }
    });

    ['dragenter', 'dragover'].forEach(event => {
        dropAreaElement.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropAreaElement.classList.add('border-blue-400', 'bg-blue-50');
        });
    });

    ['dragleave', 'drop'].forEach(event => {
        dropAreaElement.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropAreaElement.classList.remove('border-blue-400', 'bg-blue-50');
        });
    });

    dropAreaElement.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length && files[0].type.startsWith('image/')) {
            if (files[0].size > MAX_IMAGE_SIZE) {
                alert('Image size must be under 5MB.');
                return;
            }
            imageInput.files = files;
            showImagePreview(files[0]);
        } else {
            alert('Please upload a valid image file.');
        }
    });


    // Kartu Identitas Upload
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    const imageInput2 = document.getElementById('imageFile2');
    const dropAreaElement2 = document.getElementById('drop-area2');
    const placeholderElement2 = document.getElementById('placeholder2');
    const previewImage2 = document.getElementById('preview2');

    function showIdentityPreview(file) {
        if (file.size > MAX_FILE_SIZE) {
            alert('File size must be under 5MB.');
            imageInput.value = ''; // Clear file input
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage2.src = e.target.result;
            previewImage2.classList.remove('hidden');
            placeholderElement2.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }

    imageInput2.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            showIdentityPreview(this.files[0]);
        }
    });

    ['dragenter', 'dragover'].forEach(event => {
        dropAreaElement2.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropAreaElement2.classList.add('border-blue-400', 'bg-blue-50');
        });
    });

    ['dragleave', 'drop'].forEach(event => {
        dropAreaElement2.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropAreaElement2.classList.remove('border-blue-400', 'bg-blue-50');
        });
    });

    dropAreaElement2.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length && files[0].type.startsWith('image/')) {
            imageInput.files = files;
            showImagePreview(files[0]);
        } else {
            alert('Please upload a valid image file.');
        }
    });

    // Official Photo Upload
    const imageInput3 = document.getElementById('imageFile3');
    const dropAreaElement3 = document.getElementById('drop-area3');
    const placeholderElement3 = document.getElementById('placeholder3');
    const previewImage3 = document.getElementById('preview3');

    function showIdentityPreview3(file) {
        if (file.size > MAX_FILE_SIZE) {
            alert('File size must be under 5MB.');
            imageInput.value = ''; // Clear file input
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage3.src = e.target.result;
            previewImage3.classList.remove('hidden');
            placeholderElement3.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }

    imageInput3.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            showIdentityPreview3(this.files[0]);
        }
    });

    ['dragenter', 'dragover'].forEach(event => {
        dropAreaElement3.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropAreaElement3.classList.add('border-blue-400', 'bg-blue-50');
        });
    });

    ['dragleave', 'drop'].forEach(event => {
        dropAreaElement3.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
            dropAreaElement3.classList.remove('border-blue-400', 'bg-blue-50');
        });
    });

    dropAreaElement3.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length && files[0].type.startsWith('image/')) {
            imageInput3.files = files;
            showImagePreview(files[0]);
        } else {
            alert('Please upload a valid image file.');
        }
    });

    // FIle Upload
    const fileInput = document.getElementById('pdfFile');
    const fileName = document.getElementById('fileName');
    const uploadIcon = document.getElementById('uploadIcon');
    const dropArea = document.getElementById('dropArea');

    const newSvg = `
<svg height="60px" width="60px" viewBox="0 0 303.188 303.188" fill="#FB3449" xmlns="http://www.w3.org/2000/svg">
    <g>
        <polygon fill="#E8E8E8" points="219.821,0 32.842,0 32.842,303.188 270.346,303.188 270.346,50.525"></polygon>
        <polygon fill="#FB3449" points="227.64,25.263 32.842,25.263 32.842,0 219.821,0"></polygon>
        <path fill="#FB3449" d="M230.013,149.935c-3.643-6.493-16.231-8.533-22.006-9.451..."></path>
    </g>
</svg>
`;

    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            fileName.textContent = this.files[0].name;
            uploadIcon.outerHTML = newSvg;
        }
    });

    // Drag and drop functionality
    ;
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.add('bg-gray-100', 'border-blue-400');
        });
    });

    ;
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('bg-gray-100', 'border-blue-400');
        });
    });

    dropArea.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type === 'application/pdf') {
            fileInput.files = files; // update input files for form submission
            fileName.textContent = files[0].name;
            uploadIcon.outerHTML = newSvg;
        } else {
            alert('Please drop a PDF file');
        }
    });
</script>



</body>
