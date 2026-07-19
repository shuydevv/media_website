<!doctype html>
<html lang="ru">
  <body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
    <p>Здравствуйте, {{ $studentName ?? 'ученик' }}!</p>

    <p>
      Скоро наступит дата оплаты за курс <strong>«{{ $courseTitle }}»</strong> —
      {{ $dueAt->format('d.m.Y') }}.
    </p>

    <p>Пожалуйста, оплатите вовремя, чтобы доступ к курсу не был приостановлен.</p>

    <p style="color:#666">Если вы не ожидали это письмо, просто проигнорируйте его.</p>
  </body>
</html>
