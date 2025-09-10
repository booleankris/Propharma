@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>
<div class="flex justify-center items-center mt-[40px]">
    <div class="event-categories-content poppins text-[30px]">
        <div class="event-categories-items text-center">
            <img class="m-auto" src="{{ url('img/success.gif') }}" alt="">
            Pembelian Ticket Berhasil!<br>
            <b style="font-size:40px">Silahkan Cek Email Anda</b>
            <p class="poppins text-[20px] mx-[30px]">
                Ticket anda telah berhasil dikirim ke email Anda. Apabila tidak menerima email, silahkan check folder spam pada email anda.
            </p>
            {{-- <p class="poppins text-[20px]">Silahkan <a href="{{ asset('wbc.pdf') }}" download
                class="text-blue-600 underline">Download PDF</a> Surat pernyataan ini dan lanjutkan pendaftaran pada link yang dikirim melalui Email anda!</p> --}}
        </div>
    </div>
</div>

@include('landing_page.registrationfooter')
