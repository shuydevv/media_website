<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сессия истекла — {{ config('app.name', 'Школа Полтавского') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen flex items-center justify-center bg-zinc-50 px-4">
    <div class="max-w-sm w-full text-center">
        <div class="w-16 h-16 rounded-full bg-apple-blue-50 flex items-center justify-center mx-auto mb-5">
            <x-icon name="clock" class="w-8 h-8 text-apple-blue-500" />
        </div>

        <h1 class="font-oktyabrina text-2xl text-zinc-800 mb-2">Страница устарела</h1>

        <p class="text-zinc-600 text-sm mb-1">
            Похоже, страница была открыта слишком долго и сессия обновилась.
        </p>
        <p class="text-zinc-600 text-sm mb-6">
            Если вы что-то заполняли — вернитесь назад, скопируйте текст (на всякий случай) и отправьте ещё раз.
        </p>

        <div class="flex flex-col gap-2">
            <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')"
                    class="w-full bg-apple-blue-500 hover:bg-apple-blue-600 text-white font-medium rounded-xl px-4 py-3 transition">
                Вернуться назад
            </button>
            <a href="{{ url('/') }}" class="w-full text-zinc-500 hover:text-zinc-700 text-sm py-2 transition">
                На главную
            </a>
        </div>
    </div>
</body>
</html>
