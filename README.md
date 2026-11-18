# Pushpilot

**Self-hosted, zero-downtime deployments for Laravel apps on your own VPS.**
Push to GitHub, Pushpilot ships it. Live logs, one-click rollback, Discord/Slack alerts.

> 🚧 Work in progress — v1.0 under active development.

## Features (v1.0 scope)

- Connect any Ubuntu VPS over SSH (keys generated for you)
- Deploy on `git push` (GitHub webhook, HMAC-verified) or with one click
- Zero downtime: releases + atomic `current` symlink switch
- Live deploy logs in the browser (WebSockets)
- Release history and one-click rollback
- Health check with automatic rollback
- Discord, Slack and email notifications
- Encrypted secrets and `.env` editor

## Stack

Laravel 12 · Vue 3 + TypeScript + Inertia · Tailwind CSS · Laravel Reverb · Redis + Horizon ·
PostgreSQL · phpseclib · Pest · Docker Compose

## Local development

PHP, queues, websockets and databases run in Docker. Vite runs on your machine.

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (WSL 2 backend on Windows)
- PHP 8.3+ with `pdo_pgsql`, `intl`, `zip` and Composer 2 (for tooling: Pint, Larastan, Pest)
- Node.js 22+

### First run

```bash
git clone <repo-url> pushpilot && cd pushpilot
composer setup
```

`composer setup` installs PHP and npm dependencies, creates `.env` (with a fresh `APP_KEY`)
if it does not exist, builds and starts the containers, runs the migrations and builds the
assets. Then start Vite and open the app:

```bash
composer dev            # docker compose up + npm run dev
```

Open <http://localhost:8000>: the first-run screen asks you to create the owner account.
It disappears once that account exists.

> **Keep `APP_KEY` safe.** Server keys, deploy keys and `.env` contents are encrypted with it;
> regenerating it makes them unreadable.

### Everyday commands

| Task | Composer | Make (macOS/Linux/WSL) |
|---|---|---|
| Start the stack | `composer up` | `make up` |
| Stop the stack | `composer down` | `make down` |
| Start stack + Vite | `composer dev` | `make dev` |
| Style, static analysis, tests | `composer test` | `make test` |
| Fix code style | `composer lint` | `make lint` |

Artisan commands that need the database or Redis run inside the container:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
```

### Services

| Service | Purpose | From your machine |
|---|---|---|
| `app` | Laravel (PHP 8.4, `artisan serve`) | <http://localhost:8000> |
| `worker` | Horizon queue worker | dashboard at <http://localhost:8000/horizon> (owner only) |
| `reverb` | WebSocket server | `ws://localhost:8080` |
| `postgres` | PostgreSQL 18 | `localhost:5432` (`pushpilot` / `secret`) |
| `redis` | Redis 8 | `localhost:6379` |
| `target` | Fake VPS to deploy to | SSH `localhost:2222`, HTTP <http://localhost:8081> |

Ports can be changed in `.env` (`APP_PORT`, `REVERB_EXPOSED_PORT`, `DB_EXPOSED_PORT`,
`REDIS_EXPOSED_PORT`, `TARGET_SSH_PORT`, `TARGET_HTTP_PORT`).

### The `target` server

`target` is an Ubuntu 24.04 container that behaves like a freshly prepared VPS: OpenSSH
(key login only), PHP 8.3-FPM, Composer, Node 20, Nginx serving `/var/www/app/current/public`,
and a `deployer` user. Pushpilot reaches it as `target:22`.

`deployer` may run exactly one command with sudo, the same line you will add on a real server:

```
deployer ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
```

To authorize a key Pushpilot generated, append its public key to the deployer's
`authorized_keys`:

```bash
docker compose exec target sh -c 'echo "ssh-ed25519 AAAA... pushpilot" >> /home/deployer/.ssh/authorized_keys'
```

### Quality gates

Run before opening a pull request (CI runs the same checks):

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan test
npm run lint && npm run typecheck && npm run build
```

Tests use in-memory SQLite locally and PostgreSQL in CI. To run them against PostgreSQL
like CI does:

```bash
docker compose exec postgres createdb -U pushpilot pushpilot_test
docker compose exec -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_DATABASE=pushpilot_test app php artisan test
```

Real SSH tests run the `PhpseclibSshClient` against the `target` container (they authorize a
throw-away key, then remove it). They are skipped unless enabled:

```bash
docker compose up -d target
SSH_INTEGRATION=1 php artisan test --group=integration
```

### Windows notes

- Horizon needs the `pcntl`/`posix` extensions, which do not exist on Windows. `composer.json`
  declares them as platform packages so Composer installs on Windows; Horizon itself only runs
  in the Linux `worker` container.
- If another PostgreSQL (for example Laragon's) already listens on port 5432, set
  `DB_EXPOSED_PORT=5433` in `.env`. Containers are not affected.
- For faster file access, keep the project outside OneDrive-synced folders, or inside the
  WSL 2 file system.

## Documentation

- [Specification (cahier des charges)](docs/cahier-des-charges.md)
- [Architecture decision records](docs/adr/)

## License

[MIT](LICENSE) © Ghofrane Soulmi
