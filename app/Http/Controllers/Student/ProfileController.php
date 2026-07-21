<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ProfileUpdateRequest;
use App\Notifications\NotificationPreferenceRegistry;
use App\Service\FishFoodService;
use App\Service\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(FishFoodService $fish)
    {
        $user = auth()->user();

        $notificationTypes = collect(NotificationPreferenceRegistry::visible())
            ->map(fn (array $type) => $type + ['enabled' => $user->wantsNotification($type['slug'])])
            ->groupBy('group');

        $fishLevel = $fish->levelFor((int) $user->fish_total_fed);

        return view('student.profile.show', [
            'user' => $user,
            'notificationTypes' => $notificationTypes,
            'fishLevel' => $fishLevel,
            'fishLevelName' => $fish->levelName($fishLevel),
            'fishName' => $user->fish_name ?: $fish->levelName($fishLevel),
            'fishBackground' => $user->fish_background ?: config('fish.default_background'),
            'fishBackgrounds' => $fish->availableBackgrounds(),
            'fishUnlockedBackgrounds' => $fish->unlockedBackgroundsFor($user),
            'fishBackgroundPrice' => (int) config('fish.background_price'),
            'fishBalance' => (int) $user->fish_corm_balance,
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $data = $request->validated();
        $user = auth()->user();

        $user->first_name = $data['first_name'];
        $user->last_name = $data['last_name'] ?? null;
        $user->name = $data['name'];

        // "current_password"/"password" присутствуют в $data только когда
        // пользователь реально указал новый пароль (см. ProfileUpdateRequest) —
        // fill() здесь не годится: 'password' в $fillable, массовое
        // присвоение записало бы его в открытом виде без хэширования.
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('success', 'Данные сохранены.');
    }

    public function updateNotifications(Request $request)
    {
        $visibleSlugs = NotificationPreferenceRegistry::visibleSlugs();
        $enabledSlugs = array_intersect(
            (array) $request->input('enabled', []),
            $visibleSlugs
        );

        $user = auth()->user();

        // Начинаем от уже сохранённых настроек, а не с нуля: форма
        // содержит чекбоксы только для видимых слагов (см. registry ->
        // visible()), скрытые (например "Зачисление на курс") в запросе
        // просто не придут — их нельзя молча выключать при сохранении
        // остальных чекбоксов.
        $preferences = $user->notification_preferences ?? [];
        foreach ($visibleSlugs as $slug) {
            $preferences[$slug] = in_array($slug, $enabledSlugs, true);
        }

        $user->notification_preferences = $preferences;
        $user->save();

        return back()->with('success', 'Настройки уведомлений сохранены.');
    }

    public function updateCharacter(Request $request)
    {
        $data = $request->validate([
            'fish_name' => ['nullable', 'string', 'max:40'],
        ]);

        $user = auth()->user();
        $user->fish_name = $data['fish_name'] ?: null;
        $user->save();

        return back()->with('success', 'Имя персонажа сохранено.');
    }

    /**
     * Выбор среди уже открытых фонов (бесплатных или ранее купленных) —
     * покупка нового фона идёт отдельным действием, см. purchaseBackground().
     */
    public function selectBackground(Request $request, FishFoodService $fish)
    {
        $user = auth()->user();
        $unlocked = $fish->unlockedBackgroundsFor($user);

        $data = $request->validate([
            'fish_background' => ['required', 'string', Rule::in($unlocked)],
        ]);

        $user->fish_background = $data['fish_background'];
        $user->save();

        return back()->with('success', 'Фон выбран.');
    }

    public function purchaseBackground(Request $request, FishFoodService $fish)
    {
        $data = $request->validate([
            'fish_background' => ['required', 'string', Rule::in(array_keys($fish->availableBackgrounds()))],
        ]);

        $user = auth()->user();
        $slug = $data['fish_background'];

        if ($fish->isBackgroundUnlocked($user, $slug)) {
            return back()->with('success', 'Этот фон уже открыт.');
        }

        $price = (int) config('fish.background_price');
        if ((int) $user->fish_corm_balance < $price) {
            return back()->withErrors(['fish_background' => 'Недостаточно корма, чтобы открыть этот фон.']);
        }

        $unlocked = $user->fish_unlocked_backgrounds ?? [];
        $unlocked[] = $slug;

        $user->fish_corm_balance = (int) $user->fish_corm_balance - $price;
        $user->fish_unlocked_backgrounds = array_values(array_unique($unlocked));
        // Сразу выбираем купленный фон — если покупаешь, явно хочешь его применить.
        $user->fish_background = $slug;
        $user->save();

        return back()->with('success', 'Фон открыт и выбран.');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            // 5MB — это лимит на ВХОДНОЙ файл (чтобы не сжимать что-то
            // безумно огромное), на диске окажется в разы меньше — см.
            // ImageCompressor::forAvatars() (пережимает в JPEG ~70%, до 400px).
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $user = auth()->user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = ImageCompressor::forAvatars()->storeAs($request->file('avatar'), 'avatars');
        $user->save();

        return back()->with('success', 'Фото профиля обновлено.');
    }

    public function removeAvatar()
    {
        $user = auth()->user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return back()->with('success', 'Фото профиля удалено.');
    }
}
