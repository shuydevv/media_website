{{-- Одна строка воронки: $row (агрегаты из Admin\Telegram\IndexController), $label. --}}
@php
    $pct = fn ($n) => $row->starts > 0 ? round($n / $row->starts * 100) . '%' : '—';
@endphp
<tr>
    <td data-primary>{!! $label !!}</td>
    <td data-label="Старты" class="tabular-nums">{{ $row->starts }}</td>
    <td data-label="Открыли урок" class="tabular-nums">{{ (int) $row->opened }} <span class="text-zinc-400">· {{ $pct($row->opened) }}</span></td>
    <td data-label="Открыли бонус" class="tabular-nums">{{ (int) $row->opened_bonus }} <span class="text-zinc-400">· {{ $pct($row->opened_bonus) }}</span></td>
    <td data-label="Досмотрели" class="tabular-nums">{{ (int) $row->watched }} <span class="text-zinc-400">· {{ $pct($row->watched) }}</span></td>
    <td data-label="Оценили" class="tabular-nums">{{ (int) $row->rated }} <span class="text-zinc-400">· {{ $pct($row->rated) }}</span></td>
    <td data-label="🔥 / 👍 / 👎" class="tabular-nums">{{ (int) $row->great }} / {{ (int) $row->good }} / {{ (int) $row->bad }}</td>
    <td data-label="Написали" class="tabular-nums">{{ (int) $row->wrote }} <span class="text-zinc-400">· {{ $pct($row->wrote) }}</span></td>
    <td data-label="Заблокировали" class="tabular-nums">{{ (int) $row->blocked }}</td>
</tr>
