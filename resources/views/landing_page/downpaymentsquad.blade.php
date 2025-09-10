@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>
<div class="flex justify-center items-center mt-[40px]">
    <div class="event-categories-content poppins text-[30px]">
        <div class="event-categories-items text-center">
            <img class="m-auto" src="{{ url('img/success.gif') }}" alt="">
            Registrasi Tim {{ $squad->name }} Berhasil<br>
            <b style="font-size:20px">Silahkan Lakukan Pembayaran DP</b>

            @if ($payment)
                @php
                    $expired = $payment->updated_at->diffInHours(\Carbon\Carbon::now(), false) > 8;
                @endphp
                <div class="w-[100%] text-center mt-5 p-8">
                    @if ($payment->status == 'SETTLEMENT' && $payment->status_payment == 'settlement')
                        <a href="{{ route('squadregistration', [$squad->slug, $squad->code]) }}"
                            class="mt-6 w-full bg-green-600 text-white py-4 poppins font-bold px-4 rounded hover:bg-green-700">
                            Pembayaran Pendaftaran Berhasil, Klik untuk Pendataan Tim
                        </a>
                    @elseif ($payment->status == 'PENDING' && !$expired)
                        <a href="{{ $payment->payment_url }}" target="_blank"
                            class="mt-6 w-full bg-blue-600 text-white py-4 poppins font-bold px-4 rounded hover:bg-blue-700">
                            Bayar Biaya Pendaftaran
                        </a>
                    @elseif ($payment->status == 'EXPIRE' || $expired)
                    <form action="{{ route('payment.recreate') }}" method="POST"
                        enctype="multipart/form-data" class="bg-white p-8 rounded-lg shadow-lg w-full">
                        @csrf

                        @if (count($errors) > 0)
                            <div class="alert alert-danger">
                                <strong>Whoops!</strong> Ada beberapa masalah dengan inputan/masukan Anda.<br><br>
                                <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <input type="hidden" name="slug" value="{{ $squad->slug }}">
                        <input type="hidden" name="code" value="{{ $squad->code }}">
                        <input type="hidden" name="type" value="{{ $payment->type }}">
                        <input type="hidden" name="payment_code" value="{{ $payment->payment_code }}">
                        <button type="submit"
                            class="mt-6 w-full bg-yellow-600 text-white py-4 poppins font-bold px-4 rounded hover:bg-yellow-700">
                            Buat Pembayaran Ulang
                        </button>
                    </form>
                    @endif
                </div>
            @else
            
            <br>
            <b style="font-size:20px">Silahkan Lakukan Pendaftaran Ulang</b>

            @endif

        </div>
    </div>
</div>

@include('landing_page.registrationfooter')
