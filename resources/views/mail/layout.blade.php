{{--
    Общий каркас для всех писем приложения. Дочерний вид определяет только
    @section('title') (опционально), @section('preheader') (опционально —
    скрытый текст-превью в списке входящих) и @section('content') — сам
    текст письма + кнопка через @include('mail.partials.button', ...).

    Вёрстка на таблицах (стандарт для почты — не ломается в Outlook и
    других клиентах со старым движком), все стили — инлайн, плюс немного
    <style> для точечных вещей, которые инлайн не может: hover, тёмная
    тема (@media prefers-color-scheme: dark) и адаптация под мобильный
    экран (@media max-width: 600px).
--}}
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>@yield('title', 'Школа Полтавского')</title>
<style>
  body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
  body { margin: 0; padding: 0; width: 100% !important; background: #f4f4f5; }
  table { border-collapse: collapse; }
  img { border: 0; line-height: 100%; outline: none; text-decoration: none; }
  a { color: #b45309; }
  .btn a:hover { background: #27272a !important; }
  .highlight-box { background: #f4f4f5; border-radius: 10px; }
  @media screen and (max-width: 600px) {
    .container { width: 100% !important; }
    .px { padding-left: 20px !important; padding-right: 20px !important; }
    .card { border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
    .outer-pad { padding: 24px 0 !important; }
  }
  @media (prefers-color-scheme: dark) {
    body, .bg { background: #101012 !important; }
    .card { background: #19191c !important; border-color: #2a2a2e !important; }
    .text { color: #e4e4e7 !important; }
    .muted { color: #8b8b93 !important; }
    .brand { color: #e4e4e7 !important; }
    .btn a { background: #e4e4e7 !important; color: #18181b !important; }
    .highlight-box { background: #26262a !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;">
  @hasSection('preheader')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#f4f4f5;">@yield('preheader')</div>
  @endif
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="bg" style="background:#f4f4f5;">
    <tr>
      <td align="center" class="outer-pad" style="padding: 40px 16px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" class="container" style="width:600px;max-width:100%;">
          <tr>
            <td align="center" class="px" style="padding-bottom: 22px;">
              <span class="brand" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:15px;font-weight:700;color:#18181b;letter-spacing:.01em;">Школа Полтавского</span>
            </td>
          </tr>
          <tr>
            <td class="card px" style="background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;padding:36px 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td class="text" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:16px;line-height:1.65;color:#27272a;">
                    @yield('content')
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td align="center" class="px" style="padding-top: 22px;">
              <p class="muted" style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:12.5px;line-height:1.7;color:#a1a1aa;">
                Если вы не ожидали это письмо, просто проигнорируйте его.<br>
                © {{ date('Y') }} Школа Полтавского
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
