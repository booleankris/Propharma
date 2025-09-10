@extends('layouts.app')

@section('content')
    <div class="m-[19px]">
        <div>
            <h2 class="text-lg poppins-thin font-bold mt-4 mb-1">Edit Produk</h2>
        </div>

        <form class="max-w-sm mx-auto py-[6px] px-[30px]" method="POST" 
              action="{{ route('items.update', $item->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="bg-red-100 text-red-800 p-2 rounded mb-4">
                    <ul class="text-sm list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('message'))
                <div class="bg-yellow-100 text-yellow-900 p-2 rounded mb-4">
                    {{ session('message') }}
                </div>
            @endif

            @if (session('success'))
                <div class="bg-green-100 text-green-900 p-2 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="w-[100%] bg-white rounded-lg my-3">
                <label class="block mb-2 text-sm poppins font-medium text-gray-900">Foto Produk</label>

                {{-- Preview if image exists --}}
                @if ($item->item_photo)
                    <div id="previewContainer" class="mt-4 relative">
                        <img id="previewImage" src="{{ asset('uploads/items/' . $item->item_photo) }}"
                             class="w-full rounded-lg shadow-md">
                        <button type="button" id="removeImage"
                                class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded-lg hover:bg-red-600 transition">
                            Remove
                        </button>
                    </div>
                @else
                    <div id="previewContainer" class="mt-4 hidden relative">
                        <img id="previewImage" class="w-full rounded-lg shadow-md">
                        <button type="button" id="removeImage"
                                class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded-lg hover:bg-red-600 transition">
                            Remove
                        </button>
                    </div>
                @endif

                <label for="imageInput"
                       class="cursor-pointer flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-500 transition">
                    <span class="text-gray-500 text-sm">Click to upload an image</span>
                    <input type="file" name="image" id="imageInput" accept="image/*" class="hidden">
                </label>
                @error('image')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-5">
                <label for="item_name" class="block mb-2 text-sm font-medium text-gray-900">Nama Produk</label>
                <input name="item_name" type="text" value="{{ old('item_name', $item->item_name) }}"
                       class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs montserrat text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
                @error('item_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-5">
                <label for="item_desc" class="block mb-2 text-sm font-medium text-gray-900">Deskripsi</label>
                <textarea name="item_desc"
                          class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs montserrat text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">{{ old('item_desc', $item->item_desc) }}</textarea>
                @error('item_desc')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-5">
                <label for="item_price" class="block mb-2 text-sm font-medium text-gray-900">Harga Produk</label>
                <input name="item_price" type="number" value="{{ old('item_price', $item->item_price) }}"
                       class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs montserrat text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
                @error('item_price')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full px-[30px] py-[15px] transform transition duration-300 hover:scale-110 cursor-pointer bg-[#FF683F] text-center mb-[20px] text-white rounded-md">
                Simpan Perubahan
            </button>
        </form>
    </div>

    {{-- Optional: SweetAlert if needed --}}
    @if (session('info'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: @json(session('info')),
                confirmButtonText: 'OK',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'bg-[#3085d6] text-white px-4 py-2 rounded-md hover:bg-[#2563eb] focus:outline-none'
                }
            });
        </script>
    @endif
@endsection
