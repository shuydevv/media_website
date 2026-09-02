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
