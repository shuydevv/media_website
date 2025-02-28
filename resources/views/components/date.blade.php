<div class="rounded-lg border md:p-10 p-4 md:mt-16 mt-10 md:mb-20 mb-12 flex justify-center flex-col items-center">
    <img class="md:mb-3 mb-1" src="{{asset('img/calendar1.svg')}}" alt="quote">
    <p class="text-center md:text-3xl text-2xl sans font-medium text-zinc-900">{{$date}}</p>
    <div class="md:mt-3 mt-2 flex justify-center flex-col items-center">
        <!-- <img class="rounded-full w-20" src="img/portrait.jpg" alt="portrait"> -->
        <!-- <p class="not-italic mt-1 text-lg text-zinc-700 text-center">В России отменили крепостное право</p> -->
        <p class="not-italic text-base text-zinc-400 text-center">{{$fact}}<p>
    </div>
</div>