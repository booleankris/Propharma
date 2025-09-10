@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>
<div class="flex justify-center">
    <div class="event-categories-content poppins">
        <div class="event-categories-items text-center"><b style="font-size:40px">Register</b><br>
            Only Manager can register
        </div>
    </div>
</div>
<form class="max-w-sm mx-auto p-[30px]" method="POST" action="{{ route('teamregister') }}" enctype="multipart/form-data">
    @csrf

    <div class="w-[100%] bg-white rounded-lg  my-3">
        <label class="block mb-2 text-sm font-medium text-gray-900">Logo</label>

        <label for="imageInput"
            class="cursor-pointer flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-500 transition">
            <span class="text-gray-500 text-sm">Click to upload an image</span>
            <input type="file" name="image" id="imageInput" accept="image/*" class="hidden">
        </label>

        <!-- Preview Container -->
        <div id="previewContainer" class="mt-4 hidden relative">
            <img id="previewImage" class="w-full rounded-lg shadow-md">
            <!-- Remove Button -->
            <button id="removeImage"
                class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded-lg hover:bg-red-600 transition">
                Remove
            </button>
        </div>
    </div>
    <div class="mb-5">
        <label for="name" class="block mb-2 text-sm font-medium text-gray-900">Team Name</label>
        <input name="name" placeholder="Input Team Name" type="text" value="{{ old('name') }}"
            class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs poppins text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
        @error('name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-5">
        <label for="manager_name" class="block mb-2 text-sm font-medium text-gray-900">Manager Name</label>
        <input name="manager_name" placeholder="Input Manager Name" type="text" value="{{ old('manager_name') }}"
            class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs poppins text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
        @error('manager_name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-5">
        <label for="manager_email" class="block mb-2 text-sm font-medium text-gray-900">Email</label>
        <input name="manager_email" placeholder="Input Email Address" type="email" value="{{ old('manager_email') }}"
            class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs poppins text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
        @error('manager_email')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-5">
        <label for="manager_phone" class="block mb-2 text-sm font-medium text-gray-900">Phone Number</label>
        <input name="manager_phone" placeholder="Input Phone Number" type="number" value="{{ old('manager_phone') }}"
            class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs poppins text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
        @error('manager_phone')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
    <button type="submit"
        class="w-[100%] px-[30px] py-[15px] ransform transition duration-300 hover:scale-110 cursor-pointer bg-[#FF683F] text-center mb-[20px]  text-[#fff] rounded-md">
        Register
    </button>
</form>
@include('landing_page.registrationfooter')
