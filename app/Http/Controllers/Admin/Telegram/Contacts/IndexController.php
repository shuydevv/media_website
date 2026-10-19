<?php

namespace App\Http\Controllers\Admin\Telegram\Contacts;

use App\Http\Controllers\Controller;
use App\Models\TelegramContact;
use Illuminate\Http\Request;

/**
 * Все, кто запускал бота воронки, с фильтром по этапу (TelegramContact::STAGES)
 * и поиском — чтобы видеть и тех, у кого темы в группе нет (не писали).
 */
class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $stage = $request->string('stage')->toString();
        if (! array_key_exists($stage, TelegramContact::STAGES)) {
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

        $counts = ['' => $search(TelegramContact::query())->count()];
        foreach (array_keys(TelegramContact::STAGES) as $key) {
            $counts[$key] = $search(TelegramContact::query()->stage($key))->count();
        }

        $contacts = $search(TelegramContact::query()->when($stage !== '', fn ($w) => $w->stage($stage)))
            ->latest('started_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.telegram.contacts.index', compact('contacts', 'counts', 'stage', 'q'));
    }
}
