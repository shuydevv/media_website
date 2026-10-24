<?php

namespace App\Http\Controllers\Admin\Telegram\Contacts;

use App\Http\Controllers\Controller;
use App\Models\TelegramContact;
use App\Service\Telegram\TelegramBots;
use Illuminate\Http\Request;

/**
 * Все, кто запускал бота, с поиском — чтобы видеть и тех, у кого темы в
 * группе нет (не писали). Боты — вкладками (?bot=): у бота воронки фильтр по
 * этапу (TelegramContact::STAGES), у диагностики — её состояние.
 */
class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $bot = $request->string('bot')->toString();
        if (! TelegramBots::exists($bot)) {
            $bot = TelegramBots::LESSON;
        }
        $stage = $request->string('stage')->toString();
        // Этапы — воронки пробного урока; у диагностики их нет.
        if ($bot !== TelegramBots::LESSON || ! array_key_exists($stage, TelegramContact::STAGES)) {
            $stage = '';
        }
        $q = trim($request->string('q')->toString());

        $search = function ($query) use ($q) {
            if ($q === '') {
                return $query;
            }
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], ltrim($q, '@')).'%';

            return $query->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhere('username', 'like', $like)->orWhere('source', 'like', $like));
        };
        $base = fn () => TelegramContact::forBot($bot);

        $counts = ['' => $search($base())->count()];
        foreach ($bot === TelegramBots::LESSON ? array_keys(TelegramContact::STAGES) : [] as $key) {
            $counts[$key] = $search($base()->stage($key))->count();
        }
        $botCounts = collect(TelegramBots::keys())->mapWithKeys(fn ($b) => [$b => TelegramContact::forBot($b)->count()])->all();

        $contacts = $search($base()->when($stage !== '', fn ($w) => $w->stage($stage)))
            ->latest('started_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.telegram.contacts.index', compact('contacts', 'counts', 'botCounts', 'bot', 'stage', 'q'));
    }
}
