@include('landing_page.registrationheader')
<div>
    <img src="{{ url('img/register-banner.png') }}" alt="">
</div>
<div class="flex justify-center items-center mt-[40px]">
    <div class="event-categories-content poppins text-[30px]">
        <div class="event-categories-items text-center">
            <img class="m-auto" src="{{ url('img/success.gif') }}" alt="">
            Your Registration Is Fully Completed!<br>
            <b style="font-size:40px">Players and officials have been successfully uploaded!</b>
        </div>
    </div>
</div>

@include('landing_page.registrationfooter')
