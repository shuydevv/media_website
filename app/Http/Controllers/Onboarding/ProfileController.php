<?php
namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ProfileRequest;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
public function show() {
    $user = auth()->user();

    // Только часовые пояса РФ
    $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'RU');
    $currentTz = $user->timezone ?: 'Europe/Moscow';

    return view('onboarding.profile', compact('user', 'timezones', 'currentTz'));
}

    public function save(ProfileRequest $request) {
        $user = auth()->user();

        $data = $request->validated();

        // Сначала заливаем остальные поля...
        $user->fill(collect($data)->except(['password','password_confirmation'])->toArray());

        // ...а пароль пишем хэшем
        $user->password = Hash::make($data['password']);

        if (!$user->locale) {
            $user->locale = app()->getLocale();
        }
        // timezone остаётся опциональным — сохраняем только если пришёл
        if ($request->filled('timezone')) {
            $user->timezone = $request->input('timezone');
        }

        // Гейт EnsureProfileCompleted пускает дальше по этому полю — именно
        // здесь ученик впервые задаёт свой пароль, ключевой момент "теперь
        // это настоящий аккаунт, а не приглашение".
        if (!$user->profile_completed_at) {
            $user->profile_completed_at = now();
        }

        $user->save();

        return redirect()->route('student.dashboard')->with('success', 'Профиль сохранён!');
    }
}
