{{-- Вкладки раздела «Telegram-бот»: $active — analytics | diagnostic | contacts | broadcasts | texts. --}}
<x-ui.tabs class="mb-4">
    <x-ui.tab href="{{ route('admin.telegram.index') }}" :active="$active === 'analytics'">Аналитика</x-ui.tab>
    <x-ui.tab href="{{ route('admin.telegram.diagnostic') }}" :active="$active === 'diagnostic'">🧪 Диагностика</x-ui.tab>
    <x-ui.tab href="{{ route('admin.telegram.contacts.index') }}" :active="$active === 'contacts'">Контакты</x-ui.tab>
    <x-ui.tab href="{{ route('admin.telegram.broadcasts.index') }}" :active="$active === 'broadcasts'">📣 Рассылки</x-ui.tab>
    <x-ui.tab href="{{ route('admin.telegram.texts.edit') }}" :active="$active === 'texts'">Тексты сообщений</x-ui.tab>
</x-ui.tabs>
