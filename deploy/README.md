# Деплой одной командой

Один раз настраиваете — дальше деплой запускается отдельной командой
(не автоматически по пушу): `deploy.sh` на сервере соберёт новый релиз,
прогонит миграции, переключит симлинк, проверит health-check и при сбое
сам откатится.

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
`shared/.env`, `shared/storage` и sudoers для `systemctl reload php8.2-fpm`
настроены — иначе первый же автодеплой упадёт, просто вы узнаете об этом
из лога GitHub Actions, а не из терминала.
