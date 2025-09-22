<!doctype html>
<html lang="ru">
  <body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
    <p>Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

    <p>Наставник проверил(а) вашу работу: <strong>«{{ $assignmentTitle }}»</strong>.</p>

    @if($linkToResult)
      <p><a class="p-4 bg-blue-600 text-white" href="{{ $linkToResult }}">Посмотреть результат</a></p>
    @endif

    <p style="color:#666">Если вы не ожидали это письмо, просто проигнорируйте его.</p>
  </body>
</html>
