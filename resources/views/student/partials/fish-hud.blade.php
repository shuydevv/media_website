{{-- resources/views/student/partials/fish-hud.blade.php
     Сытость и радость — на квадрате с рыбой, внизу одной строкой, как
     полоска еды в Майнкрафте (решение автора 16.11.2026; прежний вариант с
     подложками под шкалой опыта — docs/fish/mood-pills-v1). По 5 значков,
     значок = 20%: полный, частично заполненный (слева направо) или пустой.
     Сытость — выбранная еда, пустая еда — тёмный силуэт. Радость — красные
     сердечки, недостающая — серые (было смайликами — походили на пуговицы). Проценты — в подсказке.
     Рендерится на дашборде и OOB-свапом в ответ на кормление
     (FishController::feed()) — так значки еды обновляются сразу.
     Стили — .fish-hud в dashboard.blade.php. --}}
@php
    $hudUser = auth()->user();
    $hudMood = app(\App\Service\FishFoodService::class)->moodFor($hudUser);
    $hudFood = $hudUser->fish_food_icon_url;
    // Заполненность каждого из 5 значков, 0–100%.
    $hudFill = fn (int $value) => array_map(fn ($i) => max(0, min(100, ($value - $i * 20) * 5)), range(0, 4));
@endphp
<div id="fish-hud" class="fish-hud" @if(!empty($hudOob)) hx-swap-oob="true" @endif
     data-satiety="{{ $hudMood['satiety'] }}">
    <div class="fish-hud-row {{ $hudMood['hungry'] ? 'is-low' : '' }} {{ $hudMood['full_satiety'] ? 'is-full' : '' }}" data-mood="satiety"
         role="img" aria-label="Сытость {{ $hudMood['satiety'] }}%" title="Сытость {{ $hudMood['satiety'] }}% — растёт, когда кормишь">
        @php
            // На нуле крайний левый — половинка еды (в цвете), а не пустой
            // силуэт: видно, что это еда и что её почти нет (решение автора
            // 16.11.2026). Только показ — значение полосы не меняется;
            // вздрагивает он, как и раньше, через is-low.
            $satietyFills = $hudFill($hudMood['satiety']);
            if ($hudMood['satiety'] <= 0) {
                $satietyFills[0] = 50;
            }
        @endphp
        @foreach ($satietyFills as $fill)
            <span class="fish-hud-icon" data-fill="{{ $fill }}">
                <img src="{{ $hudFood }}" alt="" class="fish-hud-empty">
                @if($fill > 0)
                    <span class="fish-hud-full" style="width: {{ $fill }}%"><img src="{{ $hudFood }}" alt=""></span>
                @endif
            </span>
        @endforeach
        @if($hudMood['full_satiety'])
            {{-- Сытость от 90% — еда бьётся и искрит, как сердечки. --}}
            <i class="fish-hud-spark" aria-hidden="true"></i>
            <i class="fish-hud-spark" aria-hidden="true"></i>
            <i class="fish-hud-spark" aria-hidden="true"></i>
        @endif
    </div>
    <div class="fish-hud-row {{ $hudMood['sad'] ? 'is-low' : '' }} {{ $hudMood['full_joy'] ? 'is-full' : '' }}" data-mood="joy"
         role="img" aria-label="Радость {{ $hudMood['joy'] }}%" title="Радость {{ $hudMood['joy'] }}% — растёт от тренажёров, домашек и обновок">
        @foreach ($hudFill($hudMood['joy']) as $fill)
            <span class="fish-hud-icon" data-fill="{{ $fill }}">
                {{-- Сердечки, как здоровье в Майнкрафте (смайлики походили на
                     пуговицы — 16.11.2026). Основа — пустое серое сердечко,
                     поверх — красное на долю радости. Белая обводка —
                     stroke под заливкой (paint-order), ровная по контуру. --}}
                <svg viewBox="0 2 24 21" class="fish-hud-empty fish-hud-heart fish-hud-heart--empty" aria-hidden="true">
                    <path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 3 4.5 6.7 4.5c2.1 0 3.6 1.1 5.3 3 1.7-1.9 3.2-3 5.3-3 3.7 0 5.8 3.9 4.3 7.3C19.5 16.4 12 21 12 21z"/>
                </svg>
                @if($fill > 0)
                    <span class="fish-hud-full" style="width: {{ $fill }}%">
                        <svg viewBox="0 2 24 21" class="fish-hud-heart" aria-hidden="true">
                            <path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 3 4.5 6.7 4.5c2.1 0 3.6 1.1 5.3 3 1.7-1.9 3.2-3 5.3-3 3.7 0 5.8 3.9 4.3 7.3C19.5 16.4 12 21 12 21z"/>
                            <ellipse cx="7.4" cy="8.6" rx="1.7" ry="1.1" transform="rotate(-35 7.4 8.6)" class="fish-hud-heart-shine"/>
                        </svg>
                    </span>
                @endif
            </span>
        @endforeach
        @if($hudMood['full_joy'])
            {{-- Искорки над сердечками при высокой радости (стили — .fish-hud-spark). --}}
            <i class="fish-hud-spark" aria-hidden="true"></i>
            <i class="fish-hud-spark" aria-hidden="true"></i>
            <i class="fish-hud-spark" aria-hidden="true"></i>
        @endif
    </div>
</div>
