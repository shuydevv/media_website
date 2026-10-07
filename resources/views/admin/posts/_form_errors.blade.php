@if ($errors->any())
    <x-ui.alert tone="red" class="mb-4">
        <p class="font-medium mb-1">Пост не сохранён:</p>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
        <p class="mt-2">Текст и поля сохранены в форме, а выбранные файлы браузер сбрасывает — выберите их заново.</p>
    </x-ui.alert>
@endif
