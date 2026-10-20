{{-- Краткое состояние обоих уроков контакта: открыл / % / оценка. $c — TelegramContact. --}}
@foreach ([false, true] as $bonus)
    @php
        $code = $bonus ? \App\Service\Telegram\FunnelLessons::bonusFor($c->lesson) : $c->lesson;
        $short = \App\Service\Telegram\FunnelLessons::title($code);
        $rating = $c->slot($bonus, 'rating');
    @endphp
    <div class="text-xs md:text-sm {{ $loop->first ? '' : 'mt-0.5' }}">
        <span class="text-zinc-500">{{ $bonus ? 'Бонус' : 'Урок' }} «{{ $short }}»:</span>
        @if (! $c->slot($bonus, 'opened_at'))
            <span class="text-zinc-400">не открывал</span>
        @else
            открыл
            {{-- Время — реально проигранное в плеере (без перемотки); % — если плеер сообщил длительность. --}}
            @php($watched = (int) $c->slot($bonus, 'watch_seconds'))
            @if ($watched > 0)
                · смотрел {{ $watched >= 60 ? intdiv($watched, 60).' мин' : $watched.' с' }}@if ((int) $c->slot($bonus, 'progress') > 0) ({{ (int) $c->slot($bonus, 'progress') }}%)@endif
            @else
                · <span class="text-zinc-400">просмотр не зафиксирован</span>
            @endif
            @if ($rating) · {{ \App\Service\Telegram\FunnelTexts::ratingLabel($rating) }}@if ($c->slot($bonus, 'rating_source') === 'site') <span class="text-zinc-400">(сайт)</span>@endif @endif
        @endif
    </div>
@endforeach
