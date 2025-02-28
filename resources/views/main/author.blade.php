@extends('layouts.main')
@section('title')
Школа Полтавского. Курсы по подготовке к ЕГЭ и ОГЭ по истории и обществознанию
@endsection
@section('description')
Школа Полтавского. Курсы по подготовке к ЕГЭ и ОГЭ по истории и обществознанию
@endsection
@section('content')
    <body>
        <div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-20 mb-12 px-3">
            <div class="flex md:flex-nowrap flex-wrap justify-between gap-6">
                <div>
                    <h1 class="md:text-3xl text-2xl font-medium md:mb-6 mb-4 text-start tracking-wide text-zinc-900 sans">О преподавателе и авторе сайта</h1>
                    <x-text text="Меня зовут Александр Полтавский, я преподаватель истории и обществознания." />
                    <x-text text="Увлекаюсь плаванием, шахматами, иностранными языками. В свободное время разрабатываю сайты и компьютерные программы, пишу собственный учебник истории для школьников" />
                    
                    <div class="text-xl text-zinc-700 mt-8">Образование:
                        <div>- Омский государственный университет, кафедра политологии</div>
                    </div>
                </div>
                <div><img class="max-w-sm" src="{{asset('img/shenyastarr.jpg')}}" alt="" srcset=""></div>
            </div>
        </div>

        <x-footer></x-footer>
    </body>
</html>

@endsection
