@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>
<div class="flex justify-center items-center my-[60px]">
    <div class="event-categories-content poppins text-[30px] w-[90%]">
        <div class="event-categories-items text-center">
            <p class="poppins uppercase font-bold">Pembelian Ticket</p>
            <div class="flex flex-wrap justify-center gap-4 p-4">
                <!-- Day 1 Ticket -->
                @foreach ($ticket as $ticket)
                    <a class="bg-orange-500 text-white flex items-center py-4 rounded-xl w-full sm:w-[45%] lg:w-[30%] 
                transform transition duration-300 ease-in-out 
                hover:scale-105 hover:ring-4 hover:ring-orange-300 hover:bg-orange-600 cursor-pointer shadow-lg"
                        href="{{ route('checkout', $ticket->id) }}">

                        <img src="{{ asset('img/ticketlogo.png') }}" alt="Day 1" class="w-[153px] h-[153px] mr-4" />
                        <div class="text-left">
                            <p class="text-[24px] font-bold">{{ $ticket->match_name }} TICKET</p>
                            <p class="text-[24px] font-semibold">PASS</p>
                        </div>
                    </a>
                @endforeach


                <!-- Repeat for more tickets if needed -->
            </div>
        </div>
    </div>
</div>
@include('landing_page.registrationfooter')
