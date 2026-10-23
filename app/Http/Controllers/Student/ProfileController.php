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

        // С $user — чтобы купленный сезонный фон был виден и вне сезона.
        $fishBackgrounds = $fish->availableBackgrounds($user);
        $fishAccessories = $fish->availableAccessories();
        $fishFoods = $fish->availableFoods();

        return view('student.profile.show', [
            'user' => $user,
            'notificationTypes' => $notificationTypes,
            'fishLevel' => $fishLevel,
            'fishLevelName' => $fish->levelName($fishLevel),
            'fishName' => $user->fish_name ?: $fish->levelName($fishLevel),
            'fishBackground' => $user->fish_background ?: config('fish.default_background'),
            'fishBackgrounds' => $fishBackgrounds,
            'fishUnlockedBackgrounds' => $fish->unlockedBackgroundsFor($user),
            'fishBackgroundPrices' => collect(array_keys($fishBackgrounds))
                ->mapWithKeys(fn (string $slug) => [$slug => $fish->backgroundPrice($slug)])
                ->all(),
            'fishAccessory' => $user->fish_accessory ?: 'none',
            'fishAccessories' => $fishAccessories,
            'fishUnlockedAccessories' => $fish->unlockedAccessoriesFor($user),
            'fishAccessoryEmoji' => collect(array_keys($fishAccessories))
                ->mapWithKeys(fn (string $slug) => [$slug => $fish->accessoryEmoji($slug)])
                ->all(),
            'fishAccessoryPrices' => collect(array_keys($fishAccessories))
                ->mapWithKeys(fn (string $slug) => [$slug => $fish->accessoryPrice($slug)])
                ->all(),
            'fishFood' => $user->fish_food ?: config('fish.default_food'),
            'fishFoods' => $fishFoods,
            'fishUnlockedFoods' => $fish->unlockedFoodsFor($user),
            'fishFoodPrices' => collect(array_keys($fishFoods))
                ->mapWithKeys(fn (string $slug) => [$slug => $fish->foodPrice($slug)])
                ->all(),
            'fishFoodMinLevels' => collect(array_keys($fishFoods))
                ->mapWithKeys(fn (string $slug) => [$slug => $fish->foodMinLevel($slug)])
                ->all(),
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

    /**
     * Выпустить (или перевыпустить) постоянную ссылку для входа без пароля —
     * старая сразу перестаёт работать. Сам вход — Auth\LoginLinkController.
     */
    public function issueLoginLink()
    {
        $user = auth()->user();
        abort_unless($user->canUseLoginLink(), 403);

        $hadLink = (bool) $user->login_token_hash;
        $user->issueLoginToken();

        return back()->with('success', $hadLink
            ? 'Ссылка для входа перевыпущена, старая больше не работает.'
            : 'Ссылка для входа создана.');
    }

    public function revokeLoginLink()
    {
        auth()->user()->revokeLoginToken();

        return back()->with('success', 'Вход по ссылке отключён.');
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

        // Списание и разблокировка — атомарно внутри FishFoodService (под
        // блокировкой строки пользователя), не здесь: см. комментарий у
        // FishFoodService::purchaseBackground() про гонку параллельных покупок.
        return match ($fish->purchaseBackground($user, $slug)) {
            'already_unlocked' => back()->with('success', 'Этот фон уже открыт.'),
            'insufficient' => back()->withErrors(['fish_background' => 'Недостаточно корма, чтобы открыть этот фон.']),
            default => back()->with('success', 'Фон открыт и выбран.'),
        };
    }

    /**
     * Выбор среди уже открытых аксессуаров (бесплатный "none" или ранее
     * купленные) — покупка нового отдельным действием, см. purchaseAccessory().
     * Та же пара методов, что и у фонов (select/purchase), см. выше.
     */
    public function selectAccessory(Request $request, FishFoodService $fish)
    {
        $user = auth()->user();
        $unlocked = $fish->unlockedAccessoriesFor($user);

        $data = $request->validate([
            'fish_accessory' => ['required', 'string', Rule::in($unlocked)],
        ]);

        $user->fish_accessory = $data['fish_accessory'];
        $user->save();

        return back()->with('success', 'Аксессуар выбран.');
    }

    public function purchaseAccessory(Request $request, FishFoodService $fish)
    {
        $data = $request->validate([
            'fish_accessory' => ['required', 'string', Rule::in(array_keys($fish->availableAccessories()))],
        ]);

        $user = auth()->user();
        $slug = $data['fish_accessory'];

        // Списание и разблокировка — атомарно внутри FishFoodService (под
        // блокировкой строки пользователя), не здесь: см. комментарий у
        // FishFoodService::purchaseBackground() про гонку параллельных покупок.
        return match ($fish->purchaseAccessory($user, $slug)) {
            'already_unlocked' => back()->with('success', 'Этот аксессуар уже открыт.'),
            'insufficient' => back()->withErrors(['fish_accessory' => 'Недостаточно корма, чтобы открыть этот аксессуар.']),
            default => back()->with('success', 'Аксессуар открыт и выбран.'),
        };
    }

    /**
     * Выбор среди уже открытой еды (бесплатной или ранее купленной) —
     * покупка отдельным действием, см. purchaseFood(). Та же пара методов,
     * что и у фонов/аксессуаров выше.
     */
    public function selectFood(Request $request, FishFoodService $fish)
    {
        $user = auth()->user();
        $unlocked = $fish->unlockedFoodsFor($user);

        $data = $request->validate([
            'fish_food' => ['required', 'string', Rule::in($unlocked)],
        ]);

        $user->fish_food = $data['fish_food'];
        $user->save();

        return back()->with('success', 'Еда выбрана.');
    }

    public function purchaseFood(Request $request, FishFoodService $fish)
    {
        $data = $request->validate([
            'fish_food' => ['required', 'string', Rule::in(array_keys($fish->availableFoods()))],
        ]);

        $user = auth()->user();
        $slug = $data['fish_food'];

        return match ($fish->purchaseFood($user, $slug)) {
            'already_unlocked' => back()->with('success', 'Эта еда уже открыта.'),
            'level' => back()->withErrors(['fish_food' => 'Эта еда откроется, когда акула вырастет до '.$fish->foodMinLevel($slug).' уровня.']),
            'insufficient' => back()->withErrors(['fish_food' => 'Недостаточно корма, чтобы открыть эту еду.']),
            default => back()->with('success', 'Еда открыта и выбрана.'),
        };
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
