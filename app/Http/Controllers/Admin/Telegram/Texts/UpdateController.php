<?php

namespace App\Http\Controllers\Admin\Telegram\Texts;

use App\Http\Controllers\Controller;
use App\Models\TelegramText;
use App\Service\Telegram\FunnelTexts;
use Illuminate\Http\Request;

/**
 * Сохраняем только отличающиеся от значения по умолчанию тексты: тогда
 * правка дефолта в config/telegram_funnel.php доходит до бота, если текст в
 * админке не трогали, а «вернуть как было» — это просто стереть поле.
 */
class UpdateController extends Controller
{
    public function __invoke(Request $request)
    {
        $definitions = config('telegram_funnel.texts');

        // 4096 — лимит длины сообщения в Telegram; с подписью и подстановками
        // запас нужен, поэтому меньше.
        $rules = [];
        foreach (array_keys($definitions) as $key) {
            $rules["texts.{$key}"] = ['nullable', 'string', 'max:3500'];
        }
        $data = $request->validate($rules)['texts'] ?? [];

        foreach ($definitions as $key => $def) {
            $text = trim(str_replace("\r\n", "\n", (string) ($data[$key] ?? '')));

            if ($text === '' || $text === $def['default']) {
                TelegramText::whereKey($key)->delete();
            } else {
                TelegramText::updateOrCreate(['key' => $key], ['text' => $text]);
            }
        }

        FunnelTexts::forgetCache();

        return redirect()->route('admin.telegram.texts.edit')->with('success', 'Тексты сохранены — бот использует их со следующего сообщения.');
    }
}
