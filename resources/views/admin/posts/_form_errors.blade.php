@if ($errors->any())
    <div class="mb-6 p-4 border border-red-300 bg-red-50 text-red-800 text-sm">
        <p class="font-medium mb-2">Пост не сохранён:</p>
        <ul class="list-disc ml-5 space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
        <p class="mt-3 text-red-700">Текст и поля сохранены в форме, а выбранные файлы браузер сбрасывает — выберите их заново.</p>
    </div>
@endif
