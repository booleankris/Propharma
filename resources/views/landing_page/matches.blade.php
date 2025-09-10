@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>
@foreach ($matchday as $matchday)
    <div class="text-center text-[30px] font-bold poppins">{{ $matchday->day }}</div>
    <div class="flex justify-end md:justify-start">
        <a href="{{ url('ticket') }}"
            class="px-[42px] md:px-[42px] mr-[30px] md:ml-[30px] my-[30px] bg-[#FF683F] poppins focus:outline-none text-white hover:bg-yellow-500 focus:ring-4 focus:ring-yellow-300 font-medium rounded-lg text-sm  py-2.5 me-2 mb-3 dark:focus:ring-yellow-900">
            <div class="flex items-center">
                <svg class="w-[25px] h-[25px] ml-3 mr-1" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg" stroke="#fafafa">
                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                    <g id="SVGRepo_iconCarrier">
                        <path d="M9 15L15 9" stroke="#ededed" stroke-width="1.5" stroke-linecap="round"></path>
                        <path
                            d="M15.5 14.5C15.5 15.0523 15.0523 15.5 14.5 15.5C13.9477 15.5 13.5 15.0523 13.5 14.5C13.5 13.9477 13.9477 13.5 14.5 13.5C15.0523 13.5 15.5 13.9477 15.5 14.5Z"
                            fill="#ededed"></path>
                        <path
                            d="M10.5 9.5C10.5 10.0523 10.0523 10.5 9.5 10.5C8.94772 10.5 8.5 10.0523 8.5 9.5C8.5 8.94772 8.94772 8.5 9.5 8.5C10.0523 8.5 10.5 8.94772 10.5 9.5Z"
                            fill="#ededed"></path>
                        <path
                            d="M14.0037 4H9.9963C6.21809 4 4.32899 4 3.15525 5.17157C2.27661 6.04858 2.0557 7.32572 2.00016 9.49444C1.99304 9.77248 2.22121 9.99467 2.49076 10.0652C3.35074 10.2901 3.98521 11.0711 3.98521 12C3.98521 12.9289 3.35074 13.7099 2.49076 13.9348C2.22121 14.0053 1.99304 14.2275 2.00016 14.5056C2.0557 16.6743 2.27661 17.9514 3.15525 18.8284M18 4.10041C19.3086 4.22774 20.1885 4.51654 20.8448 5.17157C21.7234 6.04858 21.9443 7.32572 21.9998 9.49444C22.007 9.77248 21.7788 9.99467 21.5092 10.0652C20.6493 10.2901 20.0148 11.0711 20.0148 12C20.0148 12.9289 20.6493 13.7099 21.5092 13.9348C21.7788 14.0053 22.007 14.2275 21.9998 14.5056C21.9443 16.6743 21.7234 17.9514 20.8448 18.8284C19.671 20 17.7819 20 14.0037 20H9.9963C8.82865 20 7.84143 20 7 19.9654"
                            stroke="#ededed" stroke-width="1.5" stroke-linecap="round"></path>
                    </g>
                </svg>
                Beli Ticket

            </div>
        </a>
    </div>
    <div
        class="space-y-8 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-300 before:to-transparent px-[32px]">
        <!-- Item #1 -->
        @foreach ($matchday->match as $key => $matches)
            @php
                $isEven = $key % 2 === 0;
            @endphp
            <div
                class="relative flex items-center justify-between md:justify-normal group is-active {{ $isEven ? 'md:flex-row' : 'md:flex-row-reverse' }}">
                <!-- Icon -->
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-full border border-white bg-slate-300 group-[.is-active]:bg-emerald-500 text-slate-500 group-[.is-active]:text-emerald-50 shadow shrink-0 md:order-1 md:group-odd:-translate-x-[-19px] md:group-even:translate-x-[-19px]">
                    <svg class="fill-current" xmlns="http://www.w3.org/2000/svg" width="12" height="10">
                        <path fill-rule="nonzero"
                            d="M10.422 1.257 4.655 7.025 2.553 4.923A.916.916 0 0 0 1.257 6.22l2.75 2.75a.916.916 0 0 0 1.296 0l6.415-6.416a.916.916 0 0 0-1.296-1.296Z" />
                    </svg>
                </div>

                <!-- Card -->
                <div
                    class="w-[calc(100%-3rem)] md:w-[calc(50%-2.5rem)] bg-white p-4 rounded border border-slate-200 shadow">
                    <div class="flex items-center justify-between space-x-2 mb-1 pb-4">
                        <div class="font-bold text-slate-900">Match {{ $key+1 }}</div>
                        <time class="font-caveat font-medium text-gray-500">{{ $matches->time }}</time>
                    </div>
                    <div class="flex justify-around items-center">
                        <div>
                            <img class="m-auto w-[60%] md:w-[100%]" src="{{ asset('img/team1.png') }}" alt="">
                            <p class="text-center py-2 font-bold poppins ">{{ $matches->homeTeam->name }}</p>
                        </div>
                        <div>
                            <p class="poppins font-bold text-[22px]">vs</p>
                        </div>
                        <div>
                            <img class="m-auto w-[60%] md:w-[100%]" src="{{ asset('img/team2.png') }}" alt="">
                            <p class="text-center py-2 font-bold poppins">{{ $matches->awayTeam->name }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>



    </div>
    <br>
@endforeach

@include('landing_page.registrationfooter')
