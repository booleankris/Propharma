@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>
<div class="flex justify-center">
    <div class="event-categories-content poppins">
        <div class="event-categories-items text-center"><b style="font-size:40px">Beli Ticket</b><br>
            Anda bisa Membeli 4 Sekaligus Ticket Dengan Satu Nama
        </div>
    </div>
</div>
<form class="max-w-sm mx-auto p-[30px]" method="POST" action="{{ route('buyticket',$ticket->id) }}" enctype="multipart/form-data">
    @csrf
    <label for="name" class="block mb-2 text-sm font-medium text-gray-900">Ticket Yang Dipilih</label>


    <a class="bg-orange-500 text-white flex items-center my-4 rounded-xl w-[100%] sm:w-[45%] lg:w-[100%]
    transform transition duration-300 ease-in-out 
    hover:scale-105 hover:ring-4 hover:ring-orange-300 p-[12px] hover:bg-orange-600 cursor-pointer shadow-lg"
        href="{{ route('checkout', $ticket->id) }}">

        <img src="{{ asset('img/ticketlogo.png') }}" alt="Day 1" class="w-[110px] h-[110px] mr-4" />
        <div class="text-left">
            <p class="text-[24px] font-bold">{{ $ticket->match_name }} TICKET</p>
            <p class="text-[24px] font-semibold">PASS</p>
        </div>
    </a>
    <div class="mb-5">
        <label for="name" class="block mb-2 text-sm font-medium text-gray-900">Nama</label>
        <input name="name" placeholder="Masukkan Nama Kamu" type="text" value="{{ old('name') }}"
            class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs poppins text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
        @error('name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div class="mb-5">
        <label for="phone" class="block mb-2 text-sm font-medium text-gray-900">Phone Number</label>
        <input name="phone" placeholder="Masukkan No.Telp Kamu" type="number"
            value="{{ old('phone') }}"
            class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs poppins text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
        @error('phone')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div class="mb-5">
        <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Email</label>
        <input name="email" placeholder="Masukkan Email Kamu" type="email" value="{{ old('email') }}"
            class="w-full border-[0.5px] border-[#FB4101] p-2 rounded-md placeholder:text-xs poppins text-[#FB4101] focus:outline focus:outline-2 focus:outline-[#FB4101]">
        @error('email')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
    
    <div class="flex items-center space-x-2">
        <label for="quantity" class="block mb-2 text-sm font-medium text-gray-900">Jumlah Ticket</label>

        <button type="button" id="decrease" class="p-2 bg-gray-200 hover:bg-gray-300 rounded-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
            </svg>
        </button>

        <input id="quantity" type="number" name="quantity" value="1" min="1" max="4"
            class="w-12 text-center border border-gray-300 rounded-md p-1 text-base" />

        <button type="button" id="increase" class="p-2 bg-gray-200 hover:bg-gray-300 rounded-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>
    <br>

    </div>


    <button type="submit"
        class="w-[100%] px-[30px] py-[15px] ransform transition duration-300 hover:scale-110 cursor-pointer bg-[#FF683F] text-center mb-[20px]  text-[#fff] rounded-md">
        Beli Sekarang
    </button>
</form>
@include('landing_page.registrationfooter')
