@extends('admin.layouts.main')

@section('title', 'Аккаунты Авито')

@section('content')
<x-ui.page-header title="Telegram-бот">
    У каждого чата с клиентом — своя тема в админ-группе («🛒 Мария · Репетитор ЕГЭ»), новые чаты — строкой в «🛒 Авито».
    Сообщение в теме клиента уходит ему с того аккаунта, на который он писал; ответы, данные в самом Авито, тоже видны в теме.
    Каждое утро аккаунты проверяются: если ключи или тариф перестали работать, бот напишет в «⚠️ Сбои».
</x-ui.page-header>

@include('admin.telegram._tabs', ['active' => 'avito'])

@unless ($webhookReady)
    <x-ui.alert tone="orange" class="mb-4">В <code>.env</code> не задан <code>AVITO_WEBHOOK_SECRET</code> — аккаунты не смогут подписаться на сообщения.</x-ui.alert>
@endunless

@if ($accounts->isEmpty())
    <x-ui.empty>Аккаунтов ещё нет — добавьте первый ниже</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr><th>Аккаунт</th><th>Профиль на Авито</th><th>Статус</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($accounts as $account)
                <tr>
                    <td data-primary>
                        {{ $account->name }}
                        <div class="text-xs text-zinc-400">client_id {{ $account->client_id }}</div>
                    </td>
                    <td data-label="Профиль" class="text-sm">
                        @if ($account->avito_user_id)
                            {{ $account->profile_name ?? '—' }} <span class="text-zinc-400">· id {{ $account->avito_user_id }}</span>
                        @else
                            <span class="text-zinc-400">не проверен</span>
                        @endif
                    </td>
                    <td data-label="Статус" class="text-sm">
                        @if ($account->last_error)
                            <x-ui.badge tone="red">Ошибка</x-ui.badge>
                            <div class="mt-1 max-w-md break-words text-xs text-zinc-500">{{ $account->last_error }}</div>
                        @elseif ($account->subscribed_at)
                            <x-ui.badge tone="green">Подключён</x-ui.badge>
                            <div class="mt-1 text-xs text-zinc-400">с {{ $account->subscribed_at->format('d.m.Y H:i') }}</div>
                        @else
                            <x-ui.badge tone="gray">Не подписан</x-ui.badge>
                        @endif
                    </td>
                    <td class="md:whitespace-nowrap">
                        <div class="flex flex-wrap items-center gap-1 md:justify-end">
                            <x-ui.action-form :action="route('admin.telegram.avito.connect', $account)" method="POST" variant="outline" class="inline">Проверить и подписать</x-ui.action-form>
                            <x-ui.action-form :action="route('admin.telegram.avito.destroy', $account)" variant="danger-soft" class="inline"
                                              confirm="Отключить «{{ $account->name }}»? Сообщения с него перестанут приходить в бота.">Удалить</x-ui.action-form>
                        </div>
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-zinc-500 md:text-right">Изменить</summary>
                            <form method="post" action="{{ route('admin.telegram.avito.update', $account) }}" class="mt-2 space-y-3 text-left">
                                @csrf
                                @method('PUT')
                                <x-ui.input name="name" label="Название" :value="$account->name" required maxlength="100" />
                                <x-ui.input name="client_id" label="client_id" :value="$account->client_id" required maxlength="100" />
                                <x-ui.input name="client_secret" label="client_secret" type="password" autocomplete="new-password"
                                            hint="Пусто — оставить прежний" />
                                <x-ui.button type="submit" size="xs">Сохранить</x-ui.button>
                            </form>
                        </details>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<x-ui.card class="mt-6 max-w-xl">
    <h2 class="mb-1 text-base font-semibold text-zinc-900">Добавить аккаунт</h2>
    <p class="mb-4 text-sm text-zinc-500">
        Ключи — на Авито в этом аккаунте: «Профиль и настройки» → «Интеграции и API» (или «Для профессионалов» → «API»).
        Нужен тариф с доступом к API мессенджера. После сохранения аккаунт сразу проверится и подпишется на сообщения.
    </p>
    <form method="post" action="{{ route('admin.telegram.avito.store') }}" class="space-y-3">
        @csrf
        <x-ui.input name="name" label="Название" :value="old('name')" required maxlength="100" hint="Как подписывать сообщения в теме, например «Репетитор ЕГЭ»" />
        <x-ui.input name="client_id" label="client_id" :value="old('client_id')" required maxlength="100" />
        <x-ui.input name="client_secret" label="client_secret" type="password" autocomplete="new-password" required />
        <x-ui.button type="submit" size="xs">Добавить и подключить</x-ui.button>
    </form>
</x-ui.card>
@endsection
