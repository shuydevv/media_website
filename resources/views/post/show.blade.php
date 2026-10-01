@extends('layouts.main')
@section('title')
{{$post->title}}
@endsection
@section('description')
{{$post->description}}
@endsection
@section('content')
@php
    // Раньше здесь был отдельный <body>...</body></html> внутри @section('content') —
    // невалидная вложенная разметка (layouts/main.blade.php уже открывает и
    // закрывает свой собственный <body>). data-protect-preps перенесён на
    // обычный div, второй <body>/<html> убраны.
    $isTherePlans = $isPlan;
@endphp
    <div data-protect-preps>
        <style>
            a:not([class]) {
                color: rgb(180 83 9);
                border-bottom: 1px dashed rgb(180 83 9);  
                padding-bottom: 2px;  
            }
            p:not([class]) {
                color: rgb(63 63 70);
                margin-top: 1rem;
                line-height: 1.625;
                
            }
            @media (min-width: 768px) { 
                p:not([class]) {
                    font-size: 1.25rem; /* 20px */
                    line-height: 1.75rem; /* 28px */
                    margin-top: 1.5rem;
            }
                a:not([class]) {
                /* color: black; */
                }

            }
        </style>
        <x-cover title1="{{$post->title}}. " title2="{{$post->title2}}" description="{{$post->description}}" :tags="$post->tags" isTherePlans="{{ $isTherePlans }}" img="{{ $post->main_image_url }}" />

        <x-block>
            {{-- Контент уже отрендерен ContentRenderer'ом (без Blade::render). --}}
            {!! $content !!}
        </x-block>

        @if($post->category)
            <x-ad_course subject="{{ $post->category->title }}" />
        @endif

        @if($isPlan)
        <div class="container mx-auto max-w-screen-md px-3 md:mb-20 mb-16">
            @if($otherPlans->isNotEmpty())
                <h2 class="md:text-3xl text-2xl text-zinc-900 font-normal tracking-wider md:mb-6 mb-4">Другие планы</h2>
                @include('plan._list', ['groups' => $otherPlans, 'compact' => true])
            @endif
            <div class="flex md:mt-10 mt-8">
                <a class="noclass md:px-8 md:py-4 px-6 py-3 border-2 border-black bg-white text-black font-semimedium tracking-wider rounded-lg" href="{{ route('plan.index') }}">Все планы <img class="inline-block ml-1" src="{{ asset('img/arrow_black-button.svg') }}" alt=""></a>
            </div>
        </div>
        @else
        <x-more_cards_div title="Другие статьи:">
            @foreach ($posts as $related)
            <a class="noclass" href="{{ $related->url }}"><x-more_card title="{{$related->title}}" title2="{{$related->title2}}" description="Подзаголовок" :tags="$related->tags" img="{{ $related->main_image_url }}" /></a>
            @endforeach

            <x-slot:pagination>
                <div class="flex justify-center md:mt-8 mt-1">
                    <a class="noclass md:px-8 md:py-4 px-6 py-3 border-2 border-black bg-white text-black font-semimedium tracking-wider rounded-lg" href="{{ route('post.index') }}">Все статьи <img class="inline-block ml-1" src="{{ asset('img/arrow_black-button.svg') }}" alt="" srcset=""></a>
                </div>
            </x-slot:pagination>
        </x-more_cards_div>
        @endif


        <x-material></x-material>
        <x-footer />
    
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
        <script>
            var swiper = new Swiper(".swiperCards", {
                slidesPerView: 1.35,
                spaceBetween: 16,
                freeMode: true,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
                breakpoints: {
                    799: {
                        slidesPerView: 3,
                        spaceBetween: 24,
                    }
                },
            });
        </script>
        <script>
// Защитить короткие слова (1–2 буквы) от "висячих" переносов
(() => {
  const SELECTOR = '[data-protect-preps]';

  // 1-буквенные: типичные предлоги/союзы
  const ONES = ['в','к','с','у','о','и','а'];

  // 2-буквенные: предлоги + союз "но" (можно править под себя)
  const TWOS = ['во','ко','по','за','из','от','до','на','об','со','но'];

  // при желании добавьте частицы:
  // const PARTICLES = ['не','ни','же','бы','ли','то'];
  const PARTICLES = [];

  const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const WORDS = [...ONES, ...TWOS, ...PARTICLES].map(esc).join('|');
  if (!WORDS) return;

  // (^|[\s(«„—-])  — граница (начало строки/пробел/открывающая пунктуация)
  // (WORDS)        — слово из белого списка (1–2 буквы)
  // ([ \t]+)       — обычные пробелы (NBSP не трогаем ⇒ скрипт идемпотентен)
  // (?=\S)         — дальше видимый символ
  const re = new RegExp(`(^|[\\s(«„—-])(${WORDS})([ \\t]+)(?=\\S)`, 'gimu');

  const skipTag = t => /^(SCRIPT|STYLE|CODE|PRE|KBD|SAMP)$/i.test(t);
  const fix = s => s.replace(re, (_, b, w) => `${b}${w}\u00A0`);

  document.querySelectorAll(SELECTOR).forEach(el => {
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, {
      acceptNode: n =>
        n.parentNode && !skipTag(n.parentNode.tagName) && /\S/.test(n.nodeValue)
          ? NodeFilter.FILTER_ACCEPT
          : NodeFilter.FILTER_REJECT
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(n => (n.nodeValue = fix(n.nodeValue)));
  });
})();
</script>
    </div>

@endsection
