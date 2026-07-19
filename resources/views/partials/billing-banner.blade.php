@php
if (!function_exists('ru_plural')) {
    function ru_plural($n, $one, $few, $many) {
        $n = abs($n);
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) return $one;
        if (in_array($mod10, [2, 3, 4]) && !in_array($mod100, [12, 13, 14])) return $few;
        return $many;
    }
}
@endphp

@foreach($billingOverdue ?? [] as $course)
    <div class="w-full bg-rose-50 text-rose-800">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3 text-sm">
            <span>Доступ к курсу «{{ $course->title }}» приостановлен — оплата не прошла.</span>
            <a href="{{ route('billing.overdue', $course) }}" class="shrink-0 underline font-medium">Оплатить вручную →</a>
        </div>
    </div>
@endforeach

@foreach($billingPromised ?? [] as $row)
    @php
        $days = $row['daysLeft'];
        $whenText = $days <= 0 ? 'сегодня' : 'через ' . $days . ' ' . ru_plural($days, 'день', 'дня', 'дней');
        $bannerId = 'billing-promise-banner-' . $row['course']->id;
        $dismissKey = 'billing_promise_dismissed_' . $row['course']->id . '_' . now()->toDateString();
    @endphp
    <div id="{{ $bannerId }}" class="w-full bg-orange-50 text-orange-800">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3 text-sm">
            <span>Доступ к курсу «{{ $row['course']->title }}» закроется {{ $whenText }} — оплатите, чтобы не потерять доступ.</span>
            <div class="flex items-center gap-4 shrink-0">
                <a href="{{ route('checkout.course.show', $row['course']) }}" class="underline font-medium">Оплатить сейчас →</a>
                <button type="button"
                        onclick="(function(el){ try { localStorage.setItem('{{ $dismissKey }}', '1'); } catch (e) {} el.remove(); })(document.getElementById('{{ $bannerId }}'))"
                        class="text-orange-600 hover:text-orange-900 text-base leading-none"
                        aria-label="Закрыть">
                    &times;
                </button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            try {
                if (localStorage.getItem('{{ $dismissKey }}') === '1') {
                    var el = document.getElementById('{{ $bannerId }}');
                    if (el) el.remove();
                }
            } catch (e) {}
        })();
    </script>
@endforeach

@foreach($billingDueSoon ?? [] as $row)
    @php
        $days = $row['daysLeft'];
        $whenText = $days <= 0 ? 'сегодня' : 'через ' . $days . ' ' . ru_plural($days, 'день', 'дня', 'дней');
    @endphp
    <div class="w-full bg-amber-50 text-amber-800">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3 text-sm">
            <span>Оплата за обучение спишется {{ $whenText }}.</span>
            <a href="{{ route('checkout.course.show', $row['course']) }}" class="shrink-0 underline font-medium">Оплатить →</a>
        </div>
    </div>
@endforeach
