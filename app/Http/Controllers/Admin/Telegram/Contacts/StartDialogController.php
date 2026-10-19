<?php

namespace App\Http\Controllers\Admin\Telegram\Contacts;

use App\Http\Controllers\Controller;
use App\Models\TelegramContact;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\TelegramApi;
use App\Service\Telegram\TelegramApiException;

/**
 * «Начать диалог» с тем, кто ничего не писал: заводим ему тему в
 * админ-группе (обычно она появляется только по его первому сообщению), и
 * админ пишет первым — боту можно, человек сам его запускал. Как только
 * админ ответил в теме, автосообщения воронки этому человеку перестают
 * уходить (TelegramContact::automationAllowed()).
 */
class StartDialogController extends Controller
{
    public function __invoke(TelegramContact $contact)
    {
        if ($contact->blocked_at) {
            return back()->with('error', 'Человек заблокировал бота — написать ему через бота нельзя.');
        }

        $topics = new AdminTopics(TelegramApi::funnel());
        $existed = (bool) $contact->topic_id;

        try {
            if (! $topics->ensureTopic($contact)) {
                return back()->with('error', 'Админ-группа не настроена: нет TELEGRAM_FUNNEL_ADMIN_CHAT_ID в .env.');
            }
        } catch (TelegramApiException $e) {
            return back()->with('error', 'Telegram не создал тему: '.$e->getMessage());
        }

        if (! $existed) {
            $topics->note($contact, '👤 Диалог начат из админки — напишите здесь первым, сообщение уйдёт человеку от бота.');
        }

        return back()->with('success', $existed ? 'Тема уже есть — откройте её в Telegram.' : 'Тема создана — откройте её в Telegram и напишите первым.');
    }
}
