@extends('admin.layouts.main')

@section('title', 'Реакции чата')

@section('content')
<x-ui.page-header title="Реакции чата" />

<p class="text-sm text-zinc-600 mb-4 max-w-2xl">
    Реакции в чате трансляций. Порядок меняется перетаскиванием строк. Реакции не удаляются,
    а выключаются: выключенную нельзя поставить заново, но там, где её уже поставили, она остаётся.
</p>

<x-ui.table>
    <thead>
        <tr>
            <th class="w-8"></th>
            <th class="w-14">Реакция</th>
            <th>Название</th>
            <th class="w-44">Где</th>
            <th class="w-20">Вкл</th>
        </tr>
    </thead>
    <tbody id="cr-rows">
        @foreach ($reactions as $r)
            @php $c = $r->toClient(); @endphp
            <tr draggable="true" data-id="{{ $r->id }}" class="{{ $r->is_active ? '' : 'opacity-50' }}">
                <td class="cursor-grab text-zinc-400 select-none" title="Перетащить">⠿</td>
                <td>
                    @if ($r->format === 'lottie')
                        <span class="text-xs text-zinc-500">Lottie</span>
                    @else
                        <img src="{{ $c['still'] ?? $c['anim'] }}" data-anim="{{ $c['anim'] }}" alt="{{ $r->emoji ?? $r->name }}" class="w-8 h-8 object-contain cr-img">
                    @endif
                </td>
                <td>
                    <input type="text" value="{{ $r->name }}" maxlength="64" data-field="name"
                           class="cr-field w-full max-w-xs rounded-lg border border-gray-200 px-2 py-1 text-sm">
                    <span class="text-xs text-zinc-400 ml-1">{{ $r->format === 'noto' ? 'стандартная' : 'своя · ' . $r->format }}</span>
                </td>
                <td>
                    <select data-field="placement" class="cr-field rounded-lg border border-gray-200 px-2 py-1 text-sm">
                        <option value="main" @selected($r->placement === 'main')>основной ряд</option>
                        <option value="extra" @selected($r->placement === 'extra')>раскрывающийся</option>
                    </select>
                </td>
                <td>
                    <input type="checkbox" data-field="is_active" class="cr-field w-4 h-4" @checked($r->is_active)>
                </td>
            </tr>
        @endforeach
    </tbody>
</x-ui.table>

<x-ui.card class="mt-6 max-w-2xl space-y-4">
    <h2 class="sans-medium text-lg text-zinc-900">Добавить свою реакцию</h2>
    <p class="text-sm text-zinc-600">
        Файл до 1 МБ: анимированный WebP или GIF, Lottie (.json) или обычная PNG —
        к PNG сайт сам добавит анимацию «прыжка». Лучше квадратная картинка на прозрачном фоне.
    </p>
    <form method="POST" action="{{ route('admin.chat-reactions.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <x-ui.input name="name" label="Название" required maxlength="64" value="{{ old('name') }}" />
        <x-ui.select name="placement" label="Где показывать">
            <option value="extra">в раскрывающемся списке</option>
            <option value="main">в основном ряду</option>
        </x-ui.select>
        <x-ui.input type="file" name="file" label="Файл" accept=".webp,.gif,.png,.json" required />
        <x-ui.button type="submit" size="xs">Добавить</x-ui.button>
    </form>
</x-ui.card>

<script>
(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const tbody = document.getElementById('cr-rows');
  const updateUrl = @json(route('admin.chat-reactions.update', 0));
  const sortUrl = @json(route('admin.chat-reactions.sort'));
  const send = (method, url, data) => fetch(url, {
    method, credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify(data),
  }).then((r) => { if (!r.ok) alert('Не сохранилось'); return r; });

  tbody.addEventListener('change', (e) => {
    const f = e.target.closest('.cr-field');
    if (!f) return;
    const tr = f.closest('tr');
    const value = f.type === 'checkbox' ? f.checked : f.value;
    if (f.dataset.field === 'name' && !String(value).trim()) return;
    send('PATCH', updateUrl.replace(/\/0$/, '/' + tr.dataset.id), { [f.dataset.field]: value });
    if (f.dataset.field === 'is_active') tr.classList.toggle('opacity-50', !value);
  });

  tbody.querySelectorAll('.cr-img').forEach((img) => {
    const still = img.src;
    img.addEventListener('mouseenter', () => { img.src = img.dataset.anim; });
    img.addEventListener('mouseleave', () => { img.src = still; });
  });

  let dragged = null;
  tbody.addEventListener('dragstart', (e) => { dragged = e.target.closest('tr'); e.dataTransfer.effectAllowed = 'move'; });
  tbody.addEventListener('dragover', (e) => {
    e.preventDefault();
    const tr = e.target.closest('tr');
    if (!dragged || !tr || tr === dragged) return;
    const r = tr.getBoundingClientRect();
    tr[e.clientY > r.top + r.height / 2 ? 'after' : 'before'](dragged);
  });
  tbody.addEventListener('drop', (e) => {
    e.preventDefault();
    dragged = null;
    send('POST', sortUrl, { ids: Array.from(tbody.children).map((tr) => Number(tr.dataset.id)) });
  });
})();
</script>
@endsection
