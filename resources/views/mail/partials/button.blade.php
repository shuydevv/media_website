{{-- Кнопка письма — таблица+инлайн-стили (bulletproof-паттерн для почты).
     Использование: @include('mail.partials.button', ['url' => $actionUrl, 'label' => 'Текст кнопки']) --}}
<table role="presentation" cellpadding="0" cellspacing="0" class="btn">
  <tr>
    <td style="border-radius:8px;background:#18181b;">
      <a href="{{ $url }}" style="display:inline-block;padding:13px 26px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">{{ $label }}</a>
    </td>
  </tr>
</table>
