@extends('layouts.app')
@section('content')
    <div class="m-[19px]">
        <div>
            <h2 class="text-lg poppins-thin font-bold mt-4 mb-1">Tambah Produk</h2>
        </div>
        <form class="max-w-sm mx-auto py-[6px] px-[30px]" method="POST" action="{{ route('items.store') }}"
            enctype="multipart/form-data">
            @csrf
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

            <div class="w-[100%] bg-white rounded-lg  my-3">
                <label class="block mb-2 text-sm poppins font-medium text-gray-900 poppins">Foto Produk</label>
                <div id="previewContainer" class="mt-4 hidden relative">
                    <img id="previewImage" class="w-full rounded-lg shadow-md">
                    <button type="button" id="removeImage"
                        class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded-lg hover:bg-red-600 transition">
                        Remove
                    </button>
                </div>
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
                <label for="item_name" class="block mb-2 text-sm font-medium text-gray-900 poppins">Nama Produk</label>
                <input name="item_name" placeholder="Masukkan Nama Barang" type="text" value="{{ old('item_name') }}"
                    class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs montserrat text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
                @error('item_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-5">
                <label for="item_desc" class="block mb-2 text-sm font-medium text-gray-900 poppins">Deskripsi</label>
                <textarea name="item_desc" placeholder="Masukkan Deskripsi Produk"
                    class="w-[100%] border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs montserrat text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]"></textarea>
                @error('item_desc')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-5">
                <label for="item_price" class="block mb-2 text-sm font-medium text-gray-900 poppins">Harga Produk</label>
                <input name="item_price" placeholder="Input Team Name" type="number" value="{{ old('item_price') }}"
                    class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs montserrat text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
                @error('item_price')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-[100%] px-[30px] py-[15px] ransform transition duration-300 hover:scale-110 cursor-pointer bg-[#FF683F] text-center mb-[20px]  text-[#fff] rounded-md">
                Tambah Produk
            </button>
        </form>
    </div>
@endsection
