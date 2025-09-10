@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>


@if ($payment)
    {{-- @dump($payment) --}}
    @php
        $expired = $payment->updated_at->diffInHours(\Carbon\Carbon::now(), false) > 8;
    @endphp

    <div class="w-[100%] text-center mt-5 p-8">

        @if ($payment->status == 'PENDING' && $expired == false)
            <div class="flex justify-center items-center mt-[40px] mb-[10px]">
                <div class="event-categories-content poppins text-[30px]">
                    <div class="event-categories-items text-center">
                        <img class="m-auto" src="{{ url('img/success.gif') }}" alt="">
                        Biaya DP Berhasil Terbayar!<br>
                        <b style="font-size:40px">Silahkan Klik Tombol di bawah ini Untuk Membayar Biaya Pelunasan </b>
                    </div>
                </div>
            </div>
            <a href="{{ $payment->payment_url }}" target="_blank"
                class="mt-6 w-full bg-blue-600 text-white py-4 poppins font-bold px-4 rounded hover:bg-blue-700">
                Bayar Biaya Pendaftaran
            </a>
            
            


        @elseif ($payment->status == 'SETTLEMENT')
            <div class="flex justify-center items-center mt-[40px]">
                <div class="event-categories-content poppins text-[30px]">
                    <div class="event-categories-items text-center">
                        <img class="m-auto" src="{{ url('img/success.gif') }}" alt="">
                        Biaya Pelunasan Berhasil Terbayar!<br>
                        <b style="font-size:40px">Pendaftaran Tim Anda Telah Sepenuhnya Selesai.</b>
                    </div>
                </div>
            </div>
            
        @elseif ($payment->status == 'EXPIRE' || $expired == true)
            <form action="{{ route('payment.recreate') }}" method="POST" enctype="multipart/form-data"
                class="bg-white p-8 rounded-lg shadow-lg w-full">
                @csrf

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
    <div class="flex justify-center">
        <div class="event-categories-content poppins">
            <div class="event-categories-items text-center"><b style="font-size:40px">Register Squad Member</b><br>
            </div>
        </div>
    </div>

    <div class="flex justify-center flex-wrap gap-2">
        <div class="md:w-[45%] w-[100%] mx-auto">
            <div class="max-w-6xl mx-auto p-6 bg-white shadow-md rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Officials</h2>
                    <a onclick="toggleModalOfficial()"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                            </path>
                        </svg>
                        Add Official
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-[100%] text-left">
                        <thead>
                            <tr>
                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block font-bold antialiased font-sans text-sm text-blue-gray-900  leading-none opacity-70">
                                        No</p>
                                </th>
                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block font-bold antialiased font-sans text-sm text-blue-gray-900  leading-none opacity-70">
                                        Official Name</p>
                                </th>
                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block font-bold px-5 antialiased font-sans text-sm text-blue-gray-900  leading-none opacity-70">
                                        Details</p>
                                </th>


                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block antialiased font-sans text-sm text-blue-gray-900 font-normal leading-none opacity-70">
                                    </p>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($official as $key => $official)
                                <tr>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-normal">
                                            {{ $key + 1 }}</p>
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ asset('uploads/official/' . $official->photo) }}"
                                                alt="Spotify"
                                                class="inline-block relative object-center !rounded-full w-12 h-12 rounded-lg border border-blue-gray-50 bg-blue-gray-50/50 object-contain p-1">
                                            <p
                                                class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-bold">
                                                {{ $official->name }}</p>
                                        </div>
                                    </td>

                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block p-5 antialiased font-sans text-sm leading-normal text-blue-gray-900 font-normal">
                                            {{ $official->details }}
                                        </p>
                                    </td>


                                    <td class="p-4 border-b border-blue-gray-50">


                                        {{-- Delete button with flat trash icon --}}

                                        <form action="{{ route('SquadOfficial.destroy', $official->id) }}"
                                            method="POST" onsubmit="return confirm('Are you sure?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1"
                                                    fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zm3.5-9h1v7h-1v-7zm5 0h-1v7h1v-7zM15.5 4l-1-1h-5l-1 1H5v2h14V4z" />
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="md:w-[50%] w-[100%] mx-auto">
            <div class="max-w-6xl mx-auto p-6 bg-white shadow-md rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Squad Member</h2>
                    <a onclick="toggleModalSquad()"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                            </path>
                        </svg>
                        Add Member
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-max table-auto text-left">
                        <thead>
                            <tr>
                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block antialiased font-sans text-sm text-blue-gray-900 font-normal leading-none opacity-70">
                                        No</p>
                                </th>
                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block antialiased font-sans text-sm text-blue-gray-900 font-normal leading-none opacity-70">
                                        Player</p>
                                </th>
                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block antialiased font-sans text-sm text-blue-gray-900 font-normal leading-none opacity-70">
                                        Number</p>
                                </th>
                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block antialiased font-sans text-sm text-blue-gray-900 font-normal leading-none opacity-70">
                                        Position</p>
                                </th>

                                <th class="border-y border-blue-gray-100 bg-blue-gray-50/50 p-4">
                                    <p
                                        class="block antialiased font-sans text-sm text-blue-gray-900 font-normal leading-none opacity-70">
                                    </p>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($member as $key => $member)
                                <tr>

                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-normal">
                                            {{ $key + 1 }}</p>
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ asset('uploads/squad/' . $member->photo) }}" alt="Spotify"
                                                class="inline-block relative object-center !rounded-full w-12 h-12 rounded-lg border border-blue-gray-50 bg-blue-gray-50/50 object-contain p-1">
                                            <p
                                                class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-bold">
                                                {{ $member->name }}</p>
                                        </div>
                                    </td>

                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block antialiased font-sans text-sm leading-normal text-blue-gray-900 font-normal">
                                            {{ $member->number }} </p>
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <div class="w-max">
                                            <div class="relative grid items-center font-sans font-bold uppercase whitespace-nowrap select-none bg-green-500/20 text-green-900 py-1 px-2 text-xs rounded-md"
                                                style="opacity: 1;">
                                                <span class=""> {{ $member->position }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="p-4 border-b border-blue-gray-50">


                                        {{-- Delete button with flat trash icon --}}
                                        <form action="{{ route('SquadMember.destroy', $member->id) }}"
                                            onsubmit="return confirm('Are you sure?')" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1"
                                                    fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zm3.5-9h1v7h-1v-7zm5 0h-1v7h1v-7zM15.5 4l-1-1h-5l-1 1H5v2h14V4z" />
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


    </div>
    @if ($totalmember >= 1 && $totalofficial >= 1 && $squad->statement_letter == null)
        <div class="w-[100%]">
            <form action="{{ route('file.upload', [$squad->slug, $squad->code]) }}" method="POST"
                enctype="multipart/form-data" class="bg-white p-8 rounded-lg shadow-lg w-full">
                @csrf
                <h1 class="text-3xl mb-1 font-semibold poppins">Upload Surat Pernyataan</h1>
                <p class="poppins">Silahkan <a href="{{ asset('wbc.pdf') }}" download
                        class="text-blue-600 underline">Download PDF</a> Surat pernyataan ini dan Upload
                    yang Sudah Ditandatangani dan Materai pada form dibawah ini</p>
                <br>
                <label for="pdfFile" id="dropArea"
                    class="block w-full border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:bg-gray-50 transition relative">
                    <div id="iconContainer" class="flex flex-col items-center justify-center pointer-events-none">
                        <!-- Initial Icon -->
                        <svg id="uploadIcon" xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-500"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4" />
                        </svg>
                        <span id="fileName" class="mt-2 text-gray-600">Click or drag to upload PDF</span>
                    </div>
                    <input type="file" name="pdfFile" id="pdfFile" accept="application/pdf" class="hidden"
                        required>
                </label>


                <button type="submit"
                    class="mt-6 w-full bg-blue-600 text-white py-4 poppins font-bold px-4 rounded hover:bg-blue-700">
                    Kirim Anggota Tim
                </button>
            </form>
        </div>
    @endif
@endif

{{-- Insert Official Modal --}}
<div id="membermodal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden transition-opacity duration-300">
    <div id="modalContent"
        class="bg-white mx-4 rounded-xl w-full max-w-md max-h-[90vh] overflow-y-auto p-6 shadow-xl transform transition-all duration-300 scale-95 opacity-0">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Add Member</h3>
            <button onclick="toggleModalSquad()" class="text-gray-500 hover:text-red-500 text-xl">&times;</button>
        </div>

        <form action="{{ route('SquadMember.store', [$squad->slug, $squad->code]) }}" method="POST"
            enctype="multipart/form-data" class="space-y-4">
            @csrf
            <h2 class="text-2xl font-semibold mb-4">Upload Foto</h2>

            <label for="imageFile" id="drop-area"
                class="block w-full border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:bg-gray-50 transition">
                <div id="placeholder" class="flex flex-col items-center justify-center">
                    <!-- Upload icon -->
                    <svg viewBox="0 0 1024 1024" class="w-16 h-16 mb-2" xmlns="http://www.w3.org/2000/svg"
                        fill="#000000">
                        <path
                            d="M736.68 435.86a173.773 173.773 0 0 1 172.042 172.038c0.578 44.907-18.093 87.822-48.461 119.698-32.761 34.387-76.991 51.744-123.581 52.343-68.202 0.876-68.284 106.718 0 105.841 152.654-1.964 275.918-125.229 277.883-277.883 1.964-152.664-128.188-275.956-277.883-277.879-68.284-0.878-68.202 104.965 0 105.842zM285.262 779.307A173.773 173.773 0 0 1 113.22 607.266c-0.577-44.909 18.09-87.823 48.461-119.705 32.759-34.386 76.988-51.737 123.58-52.337 68.2-0.877 68.284-106.721 0-105.842C132.605 331.344 9.341 454.607 7.379 607.266 5.417 759.929 135.565 883.225 285.262 885.148c68.284 0.876 68.2-104.965 0-105.841z"
                            fill="#4A5699"></path>
                        <path
                            d="M339.68 384.204a173.762 173.762 0 0 1 172.037-172.038c44.908-0.577 87.822 18.092 119.698 48.462 34.388 32.759 51.743 76.985 52.343 123.576 0.877 68.199 106.72 68.284 105.843 0-1.964-152.653-125.231-275.917-277.884-277.879-152.664-1.962-275.954 128.182-277.878 277.879-0.88 68.284 104.964 68.199 105.841 0z"
                            fill="#C45FA0"></path>
                        <path
                            d="M545.039 473.078c16.542 16.542 16.542 43.356 0 59.896l-122.89 122.895c-16.542 16.538-43.357 16.538-59.896 0-16.542-16.546-16.542-43.362 0-59.899l122.892-122.892c16.537-16.542 43.355-16.542 59.894 0z"
                            fill="#F39A2B"></path>
                        <path
                            d="M485.17 473.078c16.537-16.539 43.354-16.539 59.892 0l122.896 122.896c16.538 16.533 16.538 43.354 0 59.896-16.541 16.538-43.361 16.538-59.898 0L485.17 532.979c-16.547-16.543-16.547-43.359 0-59.901z"
                            fill="#F39A2B"></path>
                        <path
                            d="M514.045 634.097c23.972 0 43.402 19.433 43.402 43.399v178.086c0 23.968-19.432 43.398-43.402 43.398-23.964 0-43.396-19.432-43.396-43.398V677.496c0.001-23.968 19.433-43.399 43.396-43.399z"
                            fill="#E5594F"></path>
                    </svg>
                    <span class="mt-2 text-gray-600">Click atau drag untuk Upload Foto</span>
                </div>

                <img id="previewMember" class="hidden mx-auto max-h-48 mt-4 rounded" />

                <input type="file" name="imageFile" id="MemberFile" accept="image/*" class="hidden" required>
            </label>
            <div>
                <label for="membername" class="block text-sm font-medium">Nama</label>
                <input type="text" name="membername" id="membername" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />

            </div>

            <div>
                <label for="number" class="block text-sm font-medium">Nomor Punggung</label>
                <input type="number" name="number" id="number" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
                <label for="position" class="block text-sm font-medium">Posisi</label>
                <input type="text" name="position" id="position" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
                <label for="position" class="block text-sm font-medium">Tanggal Lahir</label>
                <div class="relative">
                    <input type="text" id="datepicker" name="dateofbirth"
                        class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Select date" required>
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M8 7V3M16 7V3M3 11H21M5 5H19C20.1046 5 21 5.89543 21 7V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V7C3 5.89543 3.89543 5 5 5Z" />
                        </svg>
                    </div>
                </div>
            </div>
            <h2 class="text-2xl font-semibold mb-4">Upload Kartu Identitas(KTP / KK, atau Akta Kelahiran)</h2>

            <label for="imageFile2" id="drop-area2"
                class="block w-full border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:bg-gray-50 transition">
                <div id="placeholder2" class="flex flex-col items-center justify-center">
                    <!-- Upload icon -->
                    <svg viewBox="0 0 1024 1024" class="w-16 h-16 mb-2" xmlns="http://www.w3.org/2000/svg"
                        fill="#000000">
                        <path
                            d="M736.68 435.86a173.773 173.773 0 0 1 172.042 172.038c0.578 44.907-18.093 87.822-48.461 119.698-32.761 34.387-76.991 51.744-123.581 52.343-68.202 0.876-68.284 106.718 0 105.841 152.654-1.964 275.918-125.229 277.883-277.883 1.964-152.664-128.188-275.956-277.883-277.879-68.284-0.878-68.202 104.965 0 105.842zM285.262 779.307A173.773 173.773 0 0 1 113.22 607.266c-0.577-44.909 18.09-87.823 48.461-119.705 32.759-34.386 76.988-51.737 123.58-52.337 68.2-0.877 68.284-106.721 0-105.842C132.605 331.344 9.341 454.607 7.379 607.266 5.417 759.929 135.565 883.225 285.262 885.148c68.284 0.876 68.2-104.965 0-105.841z"
                            fill="#4A5699"></path>
                        <path
                            d="M339.68 384.204a173.762 173.762 0 0 1 172.037-172.038c44.908-0.577 87.822 18.092 119.698 48.462 34.388 32.759 51.743 76.985 52.343 123.576 0.877 68.199 106.72 68.284 105.843 0-1.964-152.653-125.231-275.917-277.884-277.879-152.664-1.962-275.954 128.182-277.878 277.879-0.88 68.284 104.964 68.199 105.841 0z"
                            fill="#C45FA0"></path>
                        <path
                            d="M545.039 473.078c16.542 16.542 16.542 43.356 0 59.896l-122.89 122.895c-16.542 16.538-43.357 16.538-59.896 0-16.542-16.546-16.542-43.362 0-59.899l122.892-122.892c16.537-16.542 43.355-16.542 59.894 0z"
                            fill="#F39A2B"></path>
                        <path
                            d="M485.17 473.078c16.537-16.539 43.354-16.539 59.892 0l122.896 122.896c16.538 16.533 16.538 43.354 0 59.896-16.541 16.538-43.361 16.538-59.898 0L485.17 532.979c-16.547-16.543-16.547-43.359 0-59.901z"
                            fill="#F39A2B"></path>
                        <path
                            d="M514.045 634.097c23.972 0 43.402 19.433 43.402 43.399v178.086c0 23.968-19.432 43.398-43.402 43.398-23.964 0-43.396-19.432-43.396-43.398V677.496c0.001-23.968 19.433-43.399 43.396-43.399z"
                            fill="#E5594F"></path>
                    </svg>
                    <span class="mt-2 text-gray-600">Click atau drag untuk Upload Foto</span>
                </div>

                <img id="preview2" class="hidden mx-auto max-h-48 mt-4 rounded" />

                <input type="file" name="imageFile2" id="imageFile2" accept="image/*" class="hidden" required>
            </label>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleModalSquad()"
                    class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Save</button>
            </div>
        </form>
    </div>
</div>
<div id="officialmodal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden transition-opacity duration-300">
    <div id="modalContent2"
        class="bg-white mx-4 rounded-xl w-full max-w-md p-6 shadow-xl transform transition-all duration-300 scale-95 opacity-0">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Add Official</h3>
            <button onclick="toggleModalOfficial()" class="text-gray-500 hover:text-red-500 text-xl">&times;</button>
        </div>

        <form action="{{ route('SquadOfficial.store', [$squad->slug, $squad->code]) }}" method="POST"
            enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <h2 class="text-2xl font-semibold mb-4">Upload Foto</h2>

                <label for="imageFile3" id="drop-area3"
                    class="block w-full border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:bg-gray-50 transition">
                    <div id="placeholder3" class="flex flex-col items-center justify-center">
                        <!-- Upload icon -->
                        <svg viewBox="0 0 1024 1024" class="w-16 h-16 mb-2" xmlns="http://www.w3.org/2000/svg"
                            fill="#000000">
                            <path
                                d="M736.68 435.86a173.773 173.773 0 0 1 172.042 172.038c0.578 44.907-18.093 87.822-48.461 119.698-32.761 34.387-76.991 51.744-123.581 52.343-68.202 0.876-68.284 106.718 0 105.841 152.654-1.964 275.918-125.229 277.883-277.883 1.964-152.664-128.188-275.956-277.883-277.879-68.284-0.878-68.202 104.965 0 105.842zM285.262 779.307A173.773 173.773 0 0 1 113.22 607.266c-0.577-44.909 18.09-87.823 48.461-119.705 32.759-34.386 76.988-51.737 123.58-52.337 68.2-0.877 68.284-106.721 0-105.842C132.605 331.344 9.341 454.607 7.379 607.266 5.417 759.929 135.565 883.225 285.262 885.148c68.284 0.876 68.2-104.965 0-105.841z"
                                fill="#4A5699"></path>
                            <path
                                d="M339.68 384.204a173.762 173.762 0 0 1 172.037-172.038c44.908-0.577 87.822 18.092 119.698 48.462 34.388 32.759 51.743 76.985 52.343 123.576 0.877 68.199 106.72 68.284 105.843 0-1.964-152.653-125.231-275.917-277.884-277.879-152.664-1.962-275.954 128.182-277.878 277.879-0.88 68.284 104.964 68.199 105.841 0z"
                                fill="#C45FA0"></path>
                            <path
                                d="M545.039 473.078c16.542 16.542 16.542 43.356 0 59.896l-122.89 122.895c-16.542 16.538-43.357 16.538-59.896 0-16.542-16.546-16.542-43.362 0-59.899l122.892-122.892c16.537-16.542 43.355-16.542 59.894 0z"
                                fill="#F39A2B"></path>
                            <path
                                d="M485.17 473.078c16.537-16.539 43.354-16.539 59.892 0l122.896 122.896c16.538 16.533 16.538 43.354 0 59.896-16.541 16.538-43.361 16.538-59.898 0L485.17 532.979c-16.547-16.543-16.547-43.359 0-59.901z"
                                fill="#F39A2B"></path>
                            <path
                                d="M514.045 634.097c23.972 0 43.402 19.433 43.402 43.399v178.086c0 23.968-19.432 43.398-43.402 43.398-23.964 0-43.396-19.432-43.396-43.398V677.496c0.001-23.968 19.433-43.399 43.396-43.399z"
                                fill="#E5594F"></path>
                        </svg>
                        <span class="mt-2 text-gray-600">Click atau drag untuk Upload Foto</span>
                    </div>

                    <img id="preview3" class="hidden mx-auto max-h-48 mt-4 rounded" />

                    <input type="file" name="imageFile3" id="imageFile3" accept="image/*" class="hidden"
                        required>
                </label>
            </div>
            <div>
                <label for="officialname" class="block text-sm font-medium">Nama</label>
                <input type="text" name="officialname" id="officialname" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
                <label for="details" class="block text-sm font-medium">Keterangan</label>
                <input type="text" name="details" id="details" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleModalOfficial()"
                    class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Save</button>
            </div>
        </form>
    </div>
</div>
@include('landing_page.registrationfooter')
