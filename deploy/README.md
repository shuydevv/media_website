# Деплой одной командой

Один раз настраиваете — дальше деплой запускается отдельной командой
(не автоматически по пушу): `deploy.sh` на сервере соберёт новый релиз,
прогонит миграции, переключит симлинк, проверит health-check и при сбое
сам откатится.

## 0. Deploy-ключ, чтобы сервер мог клонировать репозиторий

`deploy.sh` тянет код по SSH (`git@github.com:shuydevv/media_website.git`),
не анонимным HTTPS — репозиторий публичный, и анонимный `git clone` вроде
как не должен требовать логин, но GitHub на практике иногда отвечает на
POST `/git-upload-pack` с хостинговых IP 401'м (anti-abuse троттлинг
анонимного git-протокола) — так и обнаружили: `deploy` на сервере стал
стабильно падать на `git clone` с "could not read Username", хотя `curl`
до github.com отвечал нормально. SSH этому не подвержен.

Разово на **сервере**, под `deploy`:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519_github_deploy -N "" -C "poltav-server-deploy-readonly"
cat ~/.ssh/id_ed25519_github_deploy.pub   # это вставляете в GitHub, см. ниже
```

Добавьте `~/.ssh/config`:

```
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_github_deploy
    IdentitiesOnly yes
```

И запись в `~/.ssh/known_hosts` — не через `ssh-keyscan` (TOFU, доверие с
первого коннекта), а вручную, официальным ed25519-ключом GitHub
(`https://api.github.com/meta` → `ssh_keys`, актуален на 2026-09-02):

```
github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl
```

Публичную часть ключа (`id_ed25519_github_deploy.pub`) добавьте в
**GitHub → репозиторий → Settings → Deploy keys → Add deploy key** —
галку "Allow write access" **не ставить**, ключу нужно только читать.

Проверка: `ssh -T git@github.com` от `deploy` должен ответить
`Hi shuydevv/media_website! You've successfully authenticated...`.

Это отдельный ключ от того, что в разделе 1 ниже — тот пускает GitHub
Actions НА сервер по SSH, этот пускает сам сервер С сервера НА GitHub за
кодом. Оба read-only/forced-command в своей роли, ни один не даёт больше
доступа, чем нужен для деплоя.

## 1. Ключ для GitHub Actions, ограниченный только деплоем

На **своей машине** (не на сервере) сгенерируйте отдельную пару ключей —
не переиспользуйте личный SSH-ключ:

```bash
ssh-keygen -t ed25519 -f gha_deploy_key -N "" -C "github-actions-deploy"
```

На **сервере**, под `deploy`, добавьте публичный ключ в `authorized_keys`
с `command=` — тогда даже если приватный ключ утечёт из GitHub, им можно
будет запустить только `deploy.sh`, и ничего больше (никакого шелла,
port forwarding и т.д.):

```bash
su - deploy
KEY_CONTENT="$(cat gha_deploy_key.pub)"   # содержимое публичного ключа
echo "command=\"bash /var/www/poltav/current/deploy/deploy.sh\",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ${KEY_CONTENT#* }" \
  | sed "s|^|ssh-ed25519 |" >> ~/.ssh/authorized_keys
```

(проще: откройте `~/.ssh/authorized_keys` руками и допишите строку вида
`command="bash /var/www/poltav/current/deploy/deploy.sh",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA...ваш ключ... github-actions-deploy`)

## 2. Секреты в GitHub

Репозиторий → Settings → Secrets and variables → Actions → New repository secret:

| Secret | Значение |
|---|---|
| `SSH_HOST` | `109.196.101.172` |
| `SSH_USER` | `deploy` |
| `SSH_PORT` | `22` (можно не создавать, если порт стандартный) |
| `SSH_PRIVATE_KEY` | содержимое файла `gha_deploy_key` (приватный ключ целиком, включая `-----BEGIN...-----`) |

После этого удалите `gha_deploy_key` и `gha_deploy_key.pub` со своей машины —
приватный ключ живёт только в GitHub Secrets.

## 3. Как это работает

Workflow — `.github/workflows/deploy.yml` — не запускается сам по пушу,
только вручную:

- через веб: вкладка **Actions** → **Deploy** → **Run workflow** (два клика);
- либо одной командой с компьютера, через [GitHub CLI](https://cli.github.com/):

  ```bash
  gh workflow run deploy.yml
  ```

  (один раз: `gh auth login`, дальше просто `gh workflow run deploy.yml`
  из папки репозитория — без переключения в браузер).

  Проверить статус последнего запуска:
  ```bash
  gh run watch
  ```

Он логинится на сервер ограниченным ключом; forced-command в
`authorized_keys` игнорирует всё, что реально прислано по SSH, и всегда
выполняет `deploy.sh`. Ветка деплоя — всегда та, что настроена в
`deploy.sh` (по умолчанию `main`); если нужно выкатить другую ветку — как
раньше, руками через `BRANCH=... bash deploy.sh` по SSH.

`concurrency: deploy-production` в workflow не даёт двум деплоям
пересечься, если запушить дважды подряд.

## 4. Первый раз

Перед тем как полагаться на автодеплой, один раз прогоните `deploy.sh`
руками на сервере (см. `main-app/deploy/deploy.sh`), чтобы убедиться, что
`shared/.env`, `shared/storage/app`, `shared/storage/logs` и sudoers для
`systemctl reload php8.2-fpm` настроены — иначе первый же автодеплой
упадёт, просто вы узнаете об этом из лога GitHub Actions, а не из
терминала.

Только `storage/app` (загрузки) и `storage/logs` — общие между релизами
(симлинк в `shared/storage`). `storage/framework/*` (скомпилированные
Blade-вьюхи, файловый кэш, сессии) — теперь локальные для каждого релиза,
создаются заново в `deploy.sh` при каждом деплое: раньше вся `storage/`
была одним общим симлинком, и `view:clear`/`view:cache` в препролёте
(шаги ДО переключения симлинка, пока старый релиз ещё живой) чистили и
пересобирали кэш, которым в этот момент реально пользовался ещё живой
трафик — конкурентная перезапись одного и того же файла компилированной
вьюхи несколькими воркерами php-fpm иногда обрывала запись на середине и
давала на проде "unexpected end of file, expecting elseif or else or
endif" на случайной админ-странице.

## 5. Воркер очереди (`queue:work`) — обязательно под systemd

Очередь (`QUEUE_CONNECTION=database`) используется почти всеми уведомлениями,
включая коды подтверждения на почту (`app/Notifications/*` в основном
`ShouldQueue`) — без работающего воркера они просто тихо копятся в `jobs`/
`failed_jobs`, а пользователь видит только "письмо не пришло". Однажды воркер
был запущен вручную (`nohup php artisan queue:work ... &`) без вообще какого-
либо супервизора — он пережил несколько деплоев на старом коде незамеченным,
пока `deploy.sh` не подчистил старые релизы, после чего каждая job стала
падать. `queue:restart` в `deploy.sh` — это лишь сигнал через кэш, который
воркер ловит МЕЖДУ джобами; без процесса, который реально его перезапускает
после штатной остановки, сигнал можно упустить навсегда.

Разово на сервере:

```bash
sudo cp /var/www/poltav/current/deploy/poltav-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now poltav-queue

# добить старый неуправляемый процесс, если он ещё жив (см. `ps aux | grep queue:work`)
kill <PID старого queue:work>
```

`deploy.sh`/`rollback.sh` после этого сами делают
`sudo systemctl restart poltav-queue` при каждом деплое/откате — нужен ещё
один passwordless-sudoers, аналогично `systemctl reload php8.2-fpm`:

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart poltav-queue, /usr/bin/systemctl reload php8.2-fpm
```

## 6. Алерт в Telegram, если очередь встала или посыпались failed_jobs

`queue:monitor-health` (расписание — каждые 5 минут, `App\Console\Kernel`)
проверяет: (а) не висит ли самая старая необработанная job в `jobs` дольше
10 минут — признак, что воркер вообще не работает; (б) не появились ли новые
`failed_jobs` с прошлой проверки. Алертит через Telegram
(`App\Service\Ops\TelegramAlert`), не почтой — именно почта чаще всего и
оказывается тем, что сломалось (см. инцидент с истёкшим тарифом SMTP:
172 писем тихо провалились в `failed_jobs`, узнали только когда студент
пожаловался, что код не пришёл).

В `shared/.env` (или в `.env` релиза, если `shared/.env` не используется)
добавьте:

```
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=...
```

Можно завести отдельного бота, либо переиспользовать токен/chat_id, уже
зашитый в `app/Http/Controllers/LeadController.php` (заявки с сайта) — тогда
алерты об инфраструктуре будут падать в тот же чат, что и лиды. Без этих
переменных `queue:monitor-health` просто пишет в `storage/logs/laravel.log`
и молча ничего никуда не шлёт — тоже лучше, чем полная тишина, но легко
пропустить.

## 7. Разобраться с уже накопившимися `failed_jobs`

После того как причина сбоя устранена (например, продлён тариф у SMTP-
провайдера), зависшие в очереди job'ы уйдут сами при следующей попытке
воркера — а вот те, что уже исчерпали `--tries=3` и осели в `failed_jobs`,
нужно вернуть в очередь руками:

```bash
cd /var/www/poltav/current && php artisan queue:retry all
```

## 8. Telegram-бот воронки пробного урока (@poltavskiy_ege_bot)

Код — `app/Service/Telegram/` (путь пользователя — в шапке `FunnelBot`),
аналитика и тексты — `/admin/telegram`. Разово на сервере:

1. **Воркер слушает очередь `telegram`.** В `poltav-queue.service` теперь
   `--queue=telegram,default` — systemd читает свою копию юнита, поэтому
   после первого деплоя с ботом её надо обновить:

   ```bash
   sudo cp /var/www/poltav/current/deploy/poltav-queue.service /etc/systemd/system/
   sudo systemctl daemon-reload && sudo systemctl restart poltav-queue
   ```

   Без этого апдейты бота копятся в `jobs` необработанными (через 10 минут
   `queue:monitor-health` пришлёт алерт о «зависшей очереди»).

2. **Планировщик.** Напоминания отправляет `telegram:send-reminders` раз в
   минуту из `schedule:run` — проверьте, что в `crontab -l` пользователя
   www-data/deploy есть `* * * * * cd /var/www/poltav/current && php artisan schedule:run`.

3. **Админ-группа.** Создать приватную группу → в настройках включить
   «Темы» → добавить @poltavskiy_ege_bot администратором с правом
   «Управление темами». Написать в группе `/chatid` — бот ответит id
   (вида `-100…`; отвечает, как только установлен webhook, шаг 5).

4. **`.env`** (в `shared/.env`):

   ```
   TELEGRAM_FUNNEL_BOT_TOKEN=...          # из @BotFather
   TELEGRAM_FUNNEL_WEBHOOK_SECRET=...     # openssl rand -hex 32
   TELEGRAM_FUNNEL_ADMIN_CHAT_ID=...      # из шага 3
   ```

   `APP_URL` должен быть боевым https-доменом — из него строятся адрес
   webhook-а и персональные ссылки на урок `/go/…`. После правки `.env` —
   `deploy`: релиз держит свою **копию** `shared/.env` (deploy.sh копирует её
   при выкладке), так что `config:cache` в `current/` новых строк не увидит.
   Дописывать в `shared/.env` — через `>>`, не пересоздавая файл (права
   `deploy:www-data 640`).

5. **Webhook:** `php artisan telegram:webhook` — заодно ставит описание бота над кнопкой «Запустить» (`config/telegram_funnel.php`, `profile`; после правки текста — запустить команду ещё раз). Состояние —
   `telegram:webhook --info`, снять — `--delete`.

Ссылки для рекламы: `t.me/poltavskiy_ege_bot?start=<урок>_<метка>`, где урок
`cho` («Человек и общество») или `er` («Экономический рост»), метка — любая
латиница/цифры/`_`/`-`, например `?start=er_vk_ad1`. Код урока можно ставить
в любом месте и регистре (`vk1_er`, `VK-ER-1`) — главное, отдельной частью
между `_`/`-`. Без кода урока — «Человек и общество».


## 9. Telegram-бот диагностики по обществознанию

Второй бот — `TelegramBots::DIAG`, сценарий `DiagBot`, тест — Mini App
`/diag` (ТЗ — `docs/diagnostic/`). Воркер, планировщик и админ-группа — те же,
что у бота воронки (раздел 8). Разово:

1. **Бот в @BotFather** — `/newbot`, имя и @username. Отдельно настраивать
   Mini App в BotFather не нужно: тест открывается кнопкой `web_app` в сообщении.
2. **Добавить бота в ту же админ-группу** администратором с правом
   «Управление темами» — он заводит темы своим людям (с маркером 🧪) и пишет
   в служебную тему «🧪 Диагностики».
3. **`.env`** (в `shared/.env`):

   ```
   TELEGRAM_DIAG_BOT_TOKEN=...          # из @BotFather
   TELEGRAM_DIAG_WEBHOOK_SECRET=...     # openssl rand -hex 32, свой, не как у воронки
   ```

   `DIAG_ALLOW_ANONYMOUS` на проде не ставить: без Telegram тест не пускает.
   Потом — `deploy` (релиз забирает копию `shared/.env` только при выкладке,
   см. раздел 8, шаг 4).
4. **Webhook:** `php artisan telegram:webhook diag` — адрес
   `/api/telegram/webhook/diag`, заодно описание бота (`config/telegram_diag.php`,
   `profile`).
5. **Миграции** (`telegram_contacts.bot`, таблицы диагностики) идут обычным деплоем.

Ссылка для рекламы: `t.me/<username>?start=<метка>` — метка любая
(латиница/цифры/`_`/`-`). Цифры — `/admin/telegram` → «🧪 Диагностика»,
люди — «Контакты» → «Бот диагностики», тексты — «Тексты сообщений».
Банк вопросов — `docs/diagnostic/questions.json`, после правки —
`php artisan diagnostic:validate`.

## 10. Сообщения Авито → тема «🛒 Авито»

У каждого чата Авито — своя тема в админ-группе бота воронки: «🛒 Мария ·
Репетитор ЕГЭ» (клиент · аккаунт). Первым сообщением в ней — карточка
(аккаунт, объявление, ссылка на чат), дальше — сообщения клиента со звуком и
ответы, данные в самом Авито, — без звука. В служебной «🛒 Авито» — строка о
каждом новом чате со ссылкой на тему. Аккаунтов может быть несколько — у
каждого свои ключи.

1. **`.env`** (в `shared/.env`), потом `deploy`:

   ```
   AVITO_WEBHOOK_SECRET=...   # openssl rand -hex 32 — часть адреса webhook-а
   ```

   `AVITO_CLIENT_ID`/`AVITO_CLIENT_SECRET` больше не нужны: если они были,
   миграция `2026_11_22_000002` перенесёт их в таблицу аккаунтом «Основной
   аккаунт» (переименовать — в админке), потом их можно убрать из `.env`.
2. **Аккаунты** — админка → «Telegram-бот» → «🛒 Авито». Ключи берутся в
   каждом аккаунте на Авито: «Профиль и настройки» → «Интеграции и API».
   Нужен тариф с доступом к API мессенджера (403 — дело в тарифе/правах).
   При добавлении аккаунт сразу проверяется и подписывается на webhook;
   ошибка видна в списке, «Проверить и подписать» — повторить. Секрет
   хранится зашифрованным (`APP_KEY`).
3. **Подписка руками** — `php artisan avito:webhook` (все аккаунты разом),
   нужна после смены домена или секрета: сначала `--delete` со старым, потом
   подписка с новым. Посмотреть — `--info`. Адрес — `/api/avito/webhook/<секрет>`
   из `APP_URL`, один на все аккаунты.
4. **Миграции** (`avito_messages`, `avito_accounts`, `avito_chats`) идут обычным деплоем;
   отправка — в очереди `telegram`, тот же воркер.
5. **Ответы из Telegram:** сообщение в теме клиента уходит ему в чат Авито с
   того аккаунта, на который он писал (только текст). Дошло — 👍 на вашем
   сообщении, нет — бот отвечает с причиной. `//` — заметка, не уходит.
   Реплай на уведомление в «🛒 Авито» (из времени до тем) тоже работает.
6. **Проверка аккаунтов** — `php artisan avito:check` каждый день в 10:00
   (планировщик): ключи, тариф, подписка. Пропала подписка — переподписывает
   сам; не вышло или ключи не работают — пишет в «⚠️ Сбои».

## 11. Чат трансляций (вместо чата Kinescope)

Код — `app/Service/LiveChat/`, правила — ТЗ «чат трансляций — этап 1». Чат сам
открывается за 15 минут до урока по расписанию или когда пошёл эфир, а закрывается
по уведомлению Kinescope «эфир закончился» (+10 минут на обрыв связи).

0. Песочница `/live-chat/sandbox` (только команда): настоящий интерфейс чата + «ученики»-боты
   по сценарию урока, всё в браузере, в базу ничего не пишется. Работает при выключенном чате —
   чтобы опробовать его до включения на уроках. Нужна только миграция (реакции берутся из базы).
1. `php artisan migrate` — таблицы чата, набор реакций по умолчанию.
2. В `.env`: `KINESCOPE_WEBHOOK_SECRET=<длинная случайная строка>`,
   `LIVE_CHAT_ENABLED=true` (по умолчанию чат выключен — на уроках чат Kinescope).
   Затем `php artisan config:cache`.
3. В Kinescope подписать уведомления об эфирах на адрес
   `https://<домен>/api/kinescope/webhook/<секрет>`.
4. Тестовый эфир 5 минут → обрыв 11 минут → снова эфир. Посмотреть, что пришло:
   `select event_type, action, lesson_id, created_at from kinescope_webhook_events order by id desc limit 20;`
   `action = ignored` при живом эфире — значит Kinescope называет события иначе:
   поправить списки `start_events`/`end_events` в `config/live_chat.php`. Урок
   находится по коду трансляции (поле «Трансляция» урока) в теле уведомления.
5. Если уведомление не пришло — вручную: `php artisan live-chat:signal <id урока> start|end`.
6. Откат на чат Kinescope — `LIVE_CHAT_ENABLED=false` и `php artisan config:cache`.

Пульт для OBS: «Вид → Доки → Пользовательские браузерные доки», адрес
`https://<домен>/live-chat/console` (сам выбирает текущий урок), один раз войти в аккаунт.
Подпись в чате («Александр Полтавский · Преподаватель») — там же, вверху пульта.
