{{--
    Планы, сгруппированные по разделам и темам (App\Service\PlanCatalog::grouped()).
    $compact — для блока «Другие планы» на странице плана: заголовки помельче.
--}}
@php $compact = $compact ?? false; @endphp
<div class="flex flex-col {{ $compact ? 'gap-8' : 'md:gap-14 gap-10' }}">
    @foreach ($groups as $group)
        <section>
            <h2 class="{{ $compact ? 'md:text-xl text-lg' : 'md:text-3xl text-2xl' }} text-zinc-900 font-normal tracking-wider md:mb-5 mb-3">
                {{ $group['section']?->title ?? 'Другие планы' }}
            </h2>

            <div class="flex flex-col {{ $compact ? 'gap-4' : 'md:gap-8 gap-6' }}">
                @foreach ($group['topics'] as $topicGroup)
                    <div>
                        @if ($topicGroup['topic'] && ($group['topics']->count() > 1 || !$compact))
                            <h3 class="sans md:text-sm text-xs uppercase tracking-wider text-zinc-400 mb-2">{{ $topicGroup['topic']->title }}</h3>
                        @endif
                        <ul class="border-t border-zinc-200">
                            @foreach ($topicGroup['plans'] as $plan)
                                <li class="border-b border-zinc-200">
                                    <a class="noclass group flex items-center justify-between gap-4 py-3 md:text-lg text-base text-zinc-900 hover:text-amber-700" href="{{ route('plan.show', ['post' => $plan->path]) }}">
                                        <span class="sans">{{ $plan->full_title }}</span>
                                        <img class="w-5 shrink-0 opacity-50 group-hover:opacity-100 transition-opacity" src="{{ asset('img/arrow.svg') }}" alt="">
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
