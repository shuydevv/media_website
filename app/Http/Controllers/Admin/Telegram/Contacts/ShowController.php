<?php

namespace App\Http\Controllers\Admin\Telegram\Contacts;

use App\Http\Controllers\Controller;
use App\Models\TelegramContact;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\ContactTimeline;

/** Карточка человека: этап воронки, история по времени, что запланировано. */
class ShowController extends Controller
{
    public function __invoke(TelegramContact $contact)
    {
        return view('admin.telegram.contacts.show', [
            'contact' => $contact,
            'timeline' => ContactTimeline::build($contact),
            'topicUrl' => AdminTopics::topicUrl($contact),
        ]);
    }
}
