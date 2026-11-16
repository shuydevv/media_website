{{-- resources/views/student/partials/fish-hud.blade.php
     Сытость и радость — на квадрате с рыбой, внизу одной строкой, как
     полоска еды в Майнкрафте (решение автора 16.11.2026; прежний вариант с
     подложками под шкалой опыта — docs/fish/mood-pills-v1). По 5 значков,
     значок = 20%: полный, частично заполненный (слева направо) или пустой.
     Сытость — выбранная еда, пустая еда — тёмный силуэт. Радость — весёлые
     смайлики, недостающая радость — грустные. Проценты — в подсказке.
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
    <div class="fish-hud-row {{ $hudMood['hungry'] ? 'is-low' : '' }}" data-mood="satiety"
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
    </div>
    <div class="fish-hud-row {{ $hudMood['sad'] ? 'is-low' : '' }}" data-mood="joy"
         role="img" aria-label="Радость {{ $hudMood['joy'] }}%" title="Радость {{ $hudMood['joy'] }}% — растёт от тренажёров, домашек и обновок">
        @foreach ($hudFill($hudMood['joy']) as $fill)
            <span class="fish-hud-icon" data-fill="{{ $fill }}">
                {{-- Основа — грустный смайлик, поверх — весёлый на долю радости. --}}
                <svg viewBox="0 0 24 24" class="fish-hud-empty fish-hud-sad" aria-hidden="true">
                    <circle cx="12" cy="12" r="11.8" class="fish-hud-ring"/>
                    <circle cx="12" cy="12" r="9.9" class="fish-hud-face"/>
                    <circle cx="8.6" cy="10" r="1.3" class="fish-hud-eye"/>
                    <circle cx="15.4" cy="10" r="1.3" class="fish-hud-eye"/>
                    <path d="M8 17.2c1.1-1.6 2.4-2.3 4-2.3s2.9.7 4 2.3" class="fish-hud-mouth"/>
                </svg>
                @if($fill > 0)
                    <span class="fish-hud-full" style="width: {{ $fill }}%">
                        <svg viewBox="0 0 24 24" class="fish-hud-happy" aria-hidden="true">
                            <circle cx="12" cy="12" r="11.8" class="fish-hud-ring"/>
                            <circle cx="12" cy="12" r="9.9" class="fish-hud-face"/>
                            <circle cx="8.6" cy="9.6" r="1.3" class="fish-hud-eye"/>
                            <circle cx="15.4" cy="9.6" r="1.3" class="fish-hud-eye"/>
                            <path d="M7.4 13.4c1 2.3 2.6 3.5 4.6 3.5s3.6-1.2 4.6-3.5" class="fish-hud-mouth"/>
                        </svg>
                    </span>
                @endif
            </span>
        @endforeach
    </div>
</div>
