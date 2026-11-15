{{-- Вкладки CRM: $active — 'index' | 'archive' | 'excluded'. --}}
<x-ui.tabs class="mb-4">
    <x-ui.tab href="{{ route('admin.crm.index') }}" :active="$active === 'index'">В работе</x-ui.tab>
    <x-ui.tab href="{{ route('admin.crm.archive') }}" :active="$active === 'archive'">Завершили / отказались</x-ui.tab>
    <x-ui.tab href="{{ route('admin.crm.excluded') }}" :active="$active === 'excluded'"
              title="Свои и тестовые аккаунты: доступ есть, но в статусы, счётчики и доход не идут">Не учитываются</x-ui.tab>
</x-ui.tabs>
