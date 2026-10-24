<?php

namespace App\Http\Controllers\Diagnostic;

use App\Http\Controllers\Controller;
use App\Models\DiagnosticAttempt;
use App\Models\TelegramContact;
use App\Service\Diagnostic\DiagnosticRunner;
use App\Service\Diagnostic\StaleAnswerException;
use App\Service\Telegram\AdminTopics;
use App\Service\Telegram\DiagBot;
use App\Service\Telegram\StartParam;
use App\Service\Telegram\TelegramBots;
use App\Service\Telegram\WebAppInitData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Страница диагностики (Mini App бота diag) и её API. Кто проходит —
 * решает session():
 *
 *   initData от Telegram (страница открыта кнопкой web_app) — подпись
 *     проверяется токеном бота diag, контакт — человек в этом боте;
 *   cookie персональной ссылки /diag/go/{token} — запасной вход без Telegram;
 *   иначе — анонимно, только если diagnostic.allow_anonymous (разработка).
 *
 * Дальше страница ходит с токеном прохождения в заголовке X-Diag-Attempt:
 * cookie внутри Telegram ненадёжны, а токен она получает в ответе session().
 */
class DiagnosticController extends Controller
{
    public const LINK_COOKIE = 'tg_diag';

    public const ANON_COOKIE = 'diag_attempt';

    public function page()
    {
        // Ведущая — маленькая акула платформы (арт — public/img/mascot/level-2,
        // для диагностики — обрезанные лёгкие копии в img/diagnostic): idle —
        // обычная, happy — верно/итог, think — вопрос/вводный экран, sad —
        // ошибка/точка роста.
        $shark = fn (string $mood) => asset("img/diagnostic/shark-{$mood}.webp");

        return view('diagnostic.app', [
            'mascot' => $shark('idle'),
            'mascots' => ['idle' => $shark('idle'), 'happy' => $shark('happy'), 'think' => $shark('think'), 'sad' => $shark('sad')],
            'background' => [
                'light' => asset(config('diagnostic.background.light')),
                'dark' => asset(config('diagnostic.background.dark')),
            ],
            'characters' => collect(config('diagnostic.characters'))
                ->map(fn ($c) => ['name' => $c['name'], 'emoji' => $c['emoji'] ?? null, 'color' => $c['color'],
                    'avatar' => $c['avatar'] ? asset($c['avatar']) : null])
                ->all(),
        ]);
    }

    /** Персональная ссылка из бота (запасной вход): cookie с токеном контакта → страница. */
    public function link(Request $request, string $token)
    {
        $contact = TelegramContact::forBot(TelegramBots::DIAG)->where('link_token', $token)->first();
        $response = redirect()->route('diag.page');

        // Превью ссылок (Telegram сам ходит по ссылкам из сообщений) cookie не нужен.
        if (! $contact || preg_match('/bot|crawler|spider|preview|facebookexternalhit/i', (string) $request->userAgent())) {
            return $response;
        }

        return $response->withCookie(cookie(self::LINK_COOKIE, $contact->link_token, 60 * 24 * 90));
    }

    public function session(Request $request, DiagnosticRunner $runner): JsonResponse
    {
        $data = $request->validate([
            'init_data' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:24'],
            'restart' => ['nullable', 'boolean'],
        ]);
        $platform = $data['platform'] ?? null;

        [$contact, $entry] = $this->resolveContact($request, (string) ($data['init_data'] ?? ''));
        if ($entry === null) {
            return response()->json(['error' => ($data['init_data'] ?? '') !== '' ? 'auth' : 'open_in_telegram'], 403);
        }

        if ($contact) {
            $attempt = $runner->latestFor($contact);
        } else {
            $token = (string) $request->cookie(self::ANON_COOKIE);
            $attempt = $token !== '' ? DiagnosticAttempt::whereNull('telegram_contact_id')->where('token', $token)->first() : null;
        }

        // «Пройти заново» — только после законченного: незаконченное продолжаем.
        $started = ! $attempt || (($data['restart'] ?? false) && $attempt->isFinished());
        if ($started) {
            $attempt = $runner->start($contact, $entry, $platform);
        }
        $this->notifyBot($contact, fn (DiagBot $bot) => $bot->opened($contact, $attempt, $started));

        $response = response()->json($runner->state($attempt) + ['in_telegram' => $entry === 'miniapp']);

        return $contact ? $response : $response->withCookie(cookie(self::ANON_COOKIE, $attempt->token, 60 * 24 * 30));
    }

    public function profile(Request $request, DiagnosticRunner $runner): JsonResponse
    {
        $attempt = $this->attempt($request);
        $data = $request->validate(['grade' => ['required', Rule::in(array_keys(config('diagnostic.grades')))]]);

        if (! $attempt->isFinished()) {
            $runner->setGrade($attempt, $data['grade']);
        }

        return response()->json($runner->state($attempt));
    }

    public function answer(Request $request, DiagnosticRunner $runner): JsonResponse
    {
        $attempt = $this->attempt($request);
        $data = $request->validate([
            'question_id' => ['required', 'string', 'max:64'],
            'answer' => ['required', 'array'],
            'guess' => ['nullable', 'boolean'],
            'answer_ms' => ['nullable', 'integer', 'min:0'],
            'explain_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $runner->answer(
                $attempt,
                $data['question_id'],
                $data['answer'],
                (bool) ($data['guess'] ?? false),
                $data['answer_ms'] ?? null,
                $data['explain_ms'] ?? null,
            );
            $this->notifyBot($attempt->contact, fn (DiagBot $bot) => $bot->answered($attempt->contact, $attempt->refresh()));

            return response()->json($result);
        } catch (StaleAnswerException $e) {
            // Повторная отправка или вторая вкладка — отдаём актуальный экран.
            return response()->json(['stale' => true, 'state' => $runner->state($e->attempt)], 409);
        }
    }

    public function target(Request $request, DiagnosticRunner $runner): JsonResponse
    {
        $attempt = $this->attempt($request);
        $data = $request->validate(['target' => ['nullable', 'integer', 'min:0', 'max:100']]);

        $wasFinished = $attempt->isFinished();
        $runner->finish($attempt, $data['target'] ?? null);
        $attempt->refresh();
        if (! $wasFinished && $attempt->isFinished()) {
            $this->notifyBot($attempt->contact, fn (DiagBot $bot) => $bot->finished($attempt->contact, $attempt));
        }

        return response()->json($runner->state($attempt));
    }

    /** «Начать заново» — посреди теста или после итога. */
    public function restart(Request $request, DiagnosticRunner $runner): JsonResponse
    {
        $old = $this->attempt($request);
        $attempt = $runner->restart($old, $request->string('platform')->toString() ?: null);
        $this->notifyBot($attempt->contact, fn (DiagBot $bot) => $bot->opened($attempt->contact, $attempt, true));

        $response = response()->json($runner->state($attempt) + ['in_telegram' => $attempt->entry === 'miniapp']);

        return $attempt->contact ? $response : $response->withCookie(cookie(self::ANON_COOKIE, $attempt->token, 60 * 24 * 30));
    }

    public function improve(Request $request, DiagnosticRunner $runner): JsonResponse
    {
        $attempt = $this->attempt($request);
        $data = $request->validate(['yes' => ['required', 'boolean']]);

        if ($runner->improve($attempt, (bool) $data['yes'], 'page')) {
            $this->notifyBot($attempt->contact, fn (DiagBot $bot) => $bot->improved($attempt->contact, $attempt, (bool) $data['yes'], 'page'));
        }

        return response()->json(['improve' => $attempt->improve]);
    }

    /**
     * Ошибка на стороне ученика (JS, не загрузился файл, сервер ответил
     * ошибкой) — иначе о поломке узнали бы только по недошедшим лидам.
     * Пишется в storage/logs/diag-client-*.log; новая ошибка (по тексту) —
     * ещё и строкой без звука в «🧪 Диагностики», не чаще раза в сутки.
     * Без CSRF (VerifyCsrfToken::$except): просроченный токен — тоже поломка,
     * о которой надо узнать.
     */
    public function clientError(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:16'],
            'message' => ['required', 'string', 'max:2000'],
            'extra' => ['nullable', 'array'],
            'attempt' => ['nullable', 'string', 'max:32'],
            'platform' => ['nullable', 'string', 'max:24'],
            'version' => ['nullable', 'string', 'max:12'],
        ]);
        $attempt = isset($data['attempt']) ? DiagnosticAttempt::where('token', $data['attempt'])->first() : null;

        Log::channel('diag_client')->warning($data['kind'].': '.$data['message'], [
            'extra' => $data['extra'] ?? null,
            'attempt' => $attempt?->id,
            'contact' => $attempt?->telegram_contact_id,
            'platform' => $data['platform'] ?? null,
            'tg_version' => $data['version'] ?? null,
            'ua' => mb_substr((string) $request->userAgent(), 0, 300),
        ]);

        $key = 'diag-client-error:'.md5($data['kind'].$data['message']);
        if (app()->environment('production') && Cache::add($key, true, now()->addDay())) {
            try {
                (new AdminTopics(TelegramBots::api(TelegramBots::DIAG)))->postToService('diag', '⚠️ <b>Ошибка в Mini App</b> ('.e($data['kind']).'): <code>'
                    .e(mb_strimwidth($data['message'], 0, 300, '…')).'</code>'
                    .($data['platform'] ?? null ? ' · '.e($data['platform']) : '')
                    ."
Подробности — storage/logs/diag-client-*.log", true);
            } catch (\Throwable $e) {
                Log::warning('Диагностика: не удалось известить об ошибке Mini App', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** @return array{0: ?TelegramContact, 1: ?string} контакт и вход; вход null — не пускать */
    private function resolveContact(Request $request, string $initData): array
    {
        if ($initData !== '') {
            $user = WebAppInitData::user($initData, (string) TelegramBots::token(TelegramBots::DIAG), (int) config('diagnostic.init_data_max_age'));
            if (! $user) {
                return [null, null];
            }

            // Открыл Mini App, а /start в боте не было (кнопка меню, прямая
            // ссылка t.me/бот/app?startapp=метка из рекламы) — заводим контакт
            // сами: человек всё равно в этом боте. Метка — из start_param.
            parse_str($initData, $fields);
            $source = StartParam::parse(is_string($fields['start_param'] ?? null) ? $fields['start_param'] : null, [], '')['source'];
            $contact = TelegramContact::firstOrCreate(
                ['bot' => TelegramBots::DIAG, 'telegram_user_id' => $user['id']],
                [
                    'username' => $user['username'] ?? null,
                    'first_name' => $user['first_name'] ?? null,
                    'last_name' => $user['last_name'] ?? null,
                    'source' => $source,
                    'link_token' => Str::random(16),
                    'started_at' => now(),
                    'last_start_at' => now(),
                ],
            );

            return [$contact, 'miniapp'];
        }

        $token = (string) $request->cookie(self::LINK_COOKIE);
        if ($token !== '' && ($contact = TelegramContact::forBot(TelegramBots::DIAG)->where('link_token', $token)->first())) {
            return [$contact, 'link'];
        }

        return config('diagnostic.allow_anonymous') ? [null, 'anonymous'] : [null, null];
    }

    /**
     * Событие теста — боту диагностики (напоминания, итог в чат, тема админу).
     * Только для прохождений из бота; сбой на стороне бота страницу не ломает.
     */
    private function notifyBot(?TelegramContact $contact, callable $call): void
    {
        if (! $contact || $contact->bot !== TelegramBots::DIAG) {
            return;
        }

        try {
            $call(DiagBot::make());
        } catch (\Throwable $e) {
            Log::error('Диагностика: событие боту не передано', ['contact' => $contact->id, 'error' => $e->getMessage()]);
        }
    }

    private function attempt(Request $request): DiagnosticAttempt
    {
        $token = (string) $request->header('X-Diag-Attempt');

        return $token !== '' ? DiagnosticAttempt::where('token', $token)->firstOrFail() : abort(403);
    }
}
