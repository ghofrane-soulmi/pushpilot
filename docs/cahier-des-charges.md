# Pushpilot — Cahier des charges (Specification)

| | |
|---|---|
| **Project** | Pushpilot — self-hosted zero-downtime deployments for Laravel apps on a VPS |
| **Author** | Ghofrane Soulmi |
| **Version** | 1.0 (draft) |
| **Status** | Ready to start |
| **License** | MIT (open source) |

---

## 1. Context and problem

Many Laravel developers and small teams host their apps on a plain VPS (Hetzner, OVH,
DigitalOcean, Contabo). They deploy by:

- SSH-ing into the server and running `git pull`, `composer install`, `php artisan migrate` by hand,
- or paying for SaaS tools (Laravel Forge + Envoyer, Ploi, RunCloud) that cost $15–40+/month,
- or writing fragile bash scripts that cause downtime, break on errors and have no history.

Consequences: downtime during deploys, no rollback, no logs, no alerts, and "who deployed what
when?" has no answer.

## 2. Vision

**Pushpilot** is an open-source, self-hosted dashboard that deploys Laravel applications to one or
more VPS servers over SSH, **with zero downtime, one-click rollback, live logs and team alerts**,
triggered automatically by a `git push`.

> "Push to GitHub. Pushpilot ships it. Roll back in one click."

## 3. Target users

| Persona | Need |
|---|---|
| Freelance Laravel developer | Deploy several client apps to cheap VPSs without paying per-server SaaS fees |
| Small agency / startup team | Shared deploy history, alerts in Discord/Slack, rollback when a release breaks |
| Learner / junior dev | Understand and apply good deployment practice (releases, symlinks, health checks) |

## 4. Objectives

### 4.1 Goals (v1.0)
1. Deploy a Laravel app from GitHub to a VPS with **zero downtime** (atomic symlink switch).
2. Trigger deploys **manually** or **automatically on push** to a chosen branch.
3. Stream deploy **logs live** in the browser.
4. Keep a **release history** and **roll back** to any of the last N releases in one click.
5. Send **notifications** (Discord, Slack, email) on success and failure.
6. Be **easy to self-host** (Docker Compose or a single install script).

### 4.2 Non-goals (v1.0)
- Provisioning servers from scratch (installing PHP, Nginx, MySQL). Pushpilot **checks** that the
  requirements exist and reports what is missing, but does not install them.
- Managing DNS, SSL certificates or databases.
- Non-Laravel frameworks (planned later through "recipes").
- Multi-tenant SaaS billing.

## 5. Glossary

| Term | Definition |
|---|---|
| **Server** | A remote Linux machine reachable over SSH |
| **Project** | A Git repository + branch deployed to a path on one server |
| **Release** | One deployed version of a project, stored in `releases/<timestamp>` |
| **Deployment** | One execution of the pipeline that creates (or activates) a release |
| **Shared paths** | Files/folders kept across releases (`.env`, `storage/`) |
| **Hook** | A user-defined command run at a given pipeline stage |
| **Current** | The `current` symlink pointing at the live release |

## 6. Roles and permissions

| Role | v1.0 | Permissions |
|---|---|---|
| **Owner** | Yes | Everything, including settings and users |
| **Member** | v1.1 | Deploy, rollback, view logs; cannot delete servers or edit secrets |
| **Viewer** | v1.1 | Read-only |

v1.0 ships with a single owner account created at install time.

## 7. Functional requirements

Priority uses MoSCoW: **M** = must (v1.0), **S** = should (v1.0 if time allows), **C** = could (later).

### 7.1 Authentication and account

| ID | Requirement | Priority |
|---|---|---|
| AUTH-1 | Log in with email + password (rate-limited) | M |
| AUTH-2 | First-run setup screen creates the owner account | M |
| AUTH-3 | Two-factor authentication (TOTP) | S |
| AUTH-4 | Personal API tokens (for a future CLI) | C |

**Acceptance:** after 5 failed logins in 1 minute the user is throttled; no route except login,
setup and the webhook endpoint is reachable without authentication.

### 7.2 Servers

| ID | Requirement | Priority |
|---|---|---|
| SRV-1 | Add a server: name, host/IP, SSH port (default 22), SSH user | M |
| SRV-2 | Pushpilot generates an **ed25519 key pair** per server; the UI shows the public key and the exact command to add it to `~/.ssh/authorized_keys` | M |
| SRV-3 | "Test connection" button: connects, runs `whoami`, shows result | M |
| SRV-4 | Requirements check: detects versions of `git`, `php`, `composer`, `node`/`npm` and PHP-FPM service name; shows ✓ / ✗ with fix hints | M |
| SRV-5 | Edit / delete a server (delete blocked while projects use it) | M |
| SRV-6 | Server status badge: reachable / unreachable / last checked at | S |
| SRV-7 | Import an existing private key instead of generating one | C |

**Acceptance:** a server with a wrong key shows a clear "Authentication failed" message within
10 seconds; the private key is **never** displayed or returned by any endpoint after creation.

### 7.3 Projects

| ID | Requirement | Priority |
|---|---|---|
| PRJ-1 | Create a project: name, server, Git repository URL, branch, deploy path (e.g. `/var/www/my-app`) | M |
| PRJ-2 | Repository access via a **deploy key** generated by Pushpilot (shown to add in GitHub → Settings → Deploy keys) | M |
| PRJ-3 | Shared paths and files, defaults: `storage/`, `.env` | M |
| PRJ-4 | `.env` editor: stored **encrypted**, written to the server's shared folder on save | M |
| PRJ-5 | Build settings: run `composer install` (on by default), `npm ci && npm run build` (toggle), migrations (toggle) | M |
| PRJ-6 | Custom hooks: commands before/after `build`, before/after `activate` | S |
| PRJ-7 | PHP-FPM service to reload (e.g. `php8.3-fpm`), optional queue restart (`php artisan queue:restart`) | M |
| PRJ-8 | Health-check URL + expected HTTP status; failure triggers automatic rollback (toggle) | S |
| PRJ-9 | Number of releases to keep (default 5) | M |
| PRJ-10 | "Initialize" action: creates `releases/`, `shared/` structure on the server | M |

### 7.4 Deployments

| ID | Requirement | Priority |
|---|---|---|
| DEP-1 | Manual "Deploy now" button (latest commit of the branch) | M |
| DEP-2 | **GitHub webhook** endpoint per project; deploys on push to the configured branch | M |
| DEP-3 | Webhook requests are verified with the **HMAC SHA-256 signature** (`X-Hub-Signature-256`); invalid → 401 | M |
| DEP-4 | Deployments run in a **queued job**, one at a time per project (lock); a new push while deploying is queued | M |
| DEP-5 | **Live log streaming** to the browser through WebSockets (Laravel Reverb), line by line, per step | M |
| DEP-6 | Each deployment stores: trigger (manual/webhook), user, commit hash, commit message, author, start/end time, duration, status, full log | M |
| DEP-7 | Statuses: `queued`, `running`, `succeeded`, `failed`, `cancelled`, `rolled_back` | M |
| DEP-8 | Cancel a queued or running deployment (running: stops after the current step, never leaves `current` broken) | S |
| DEP-9 | Step timeout (default 10 min) and global timeout (default 30 min) | M |
| DEP-10 | Re-deploy a previous commit | S |

**Acceptance:** during a deployment the live site keeps answering HTTP 200 (verified with a
loop of `curl` requests); a failing step marks the deployment `failed` and `current` still
points at the previous release.

### 7.5 Releases and rollback

| ID | Requirement | Priority |
|---|---|---|
| REL-1 | List releases on the server with commit, date, and which is live | M |
| REL-2 | **One-click rollback**: switch `current` to the chosen release, reload PHP-FPM | M |
| REL-3 | Automatic cleanup: keep the last N releases, delete older ones | M |
| REL-4 | Rollback is logged as a deployment of type `rollback` | M |

**Acceptance:** rollback completes in under 5 seconds on a standard VPS.

### 7.6 Notifications

| ID | Requirement | Priority |
|---|---|---|
| NOT-1 | Channels per project: Discord webhook, Slack webhook, email | M (Discord, Slack), S (email) |
| NOT-2 | Events: deployment started, succeeded, failed, rolled back | M |
| NOT-3 | Message includes project, branch, commit (short hash + message), author, duration, link to the log | M |
| NOT-4 | "Send test notification" button | S |

### 7.7 Monitoring (v1.1)

| ID | Requirement | Priority |
|---|---|---|
| MON-1 | Collect CPU load, memory, disk usage every minute over SSH | C |
| MON-2 | Thresholds and alerts (e.g. disk > 90%) | C |
| MON-3 | Cron heartbeat URLs: alert if a scheduled task did not ping in time | C |
| MON-4 | Charts on the server page (last 24 h) | C |

### 7.8 Activity log

| ID | Requirement | Priority |
|---|---|---|
| ACT-1 | Record who did what and when: server/project created or edited, deploy, rollback, env edited (never the env content) | M |
| ACT-2 | Filter by project and user | S |

### 7.9 Dashboard (home)

| ID | Requirement | Priority |
|---|---|---|
| DSH-1 | List of projects with live release, last deployment status, last deploy time | M |
| DSH-2 | Recent deployments feed (live-updating) | M |
| DSH-3 | Server reachability overview | S |

## 8. Deployment pipeline (technical specification)

### 8.1 Directory layout on the target server

```
/var/www/my-app/
├── current -> releases/20261116143000   # symlink, web root = current/public
├── releases/
│   ├── 20261116143000/
│   └── 20261115091200/
└── shared/
    ├── .env
    └── storage/
```

Nginx (configured once by the user) points to `/var/www/my-app/current/public`.

### 8.2 Steps

| # | Step | Command(s) (simplified) | On failure |
|---|---|---|---|
| 1 | Prepare | `mkdir -p releases/$R shared/storage` | stop, `failed` |
| 2 | Clone | `git clone --depth 1 --branch $BRANCH $REPO releases/$R` (via deploy key) | stop, delete `releases/$R` |
| 3 | Link shared | `ln -nfs shared/.env releases/$R/.env`, replace `storage/` with symlink | stop, delete release |
| 4 | Composer | `composer install --no-dev --optimize-autoloader --no-interaction` | stop, delete release |
| 5 | Assets | `npm ci && npm run build` (if enabled) | stop, delete release |
| 6 | Hooks (before activate) | user commands | stop, delete release |
| 7 | Migrate | `php artisan migrate --force` (if enabled) | stop, `failed` (DB may have changed: shown as warning) |
| 8 | Optimize | `php artisan optimize` | stop, delete release |
| 9 | **Activate** | `ln -nfs releases/$R current.tmp && mv -Tf current.tmp current` (atomic) | — |
| 10 | Reload | `sudo systemctl reload php8.3-fpm`, `php artisan queue:restart` | warn |
| 11 | Health check | `GET` health URL, expect status | auto-rollback if enabled |
| 12 | Cleanup | delete releases beyond N | warn only |

### 8.3 Rules
- Every value placed in a shell command is escaped (`escapeshellarg`); no user input is ever
  concatenated raw into a command.
- Commands run as the configured SSH user; Pushpilot needs `sudo` **only** for the PHP-FPM reload
  (documented `sudoers` line limited to that command).
- Output is streamed line by line, prefixed by step, and secrets (values from `.env`) are masked.
- A deployment never leaves the `current` symlink pointing at an incomplete release.

## 9. Non-functional requirements

### 9.1 Security
- SSH private keys, deploy keys, webhook secrets and `.env` contents are **encrypted at rest**
  (Laravel encrypted casts, `APP_KEY`).
- Secrets are never logged, never sent to the browser after creation, never included in exports.
- CSRF protection on all forms; webhook route excluded from CSRF but protected by HMAC.
- Rate limiting on login and webhook endpoints.
- Authorization through Laravel policies on every action.
- Security headers (CSP, X-Frame-Options, HSTS in production).
- Dependencies checked in CI (`composer audit`, `npm audit`).

### 9.2 Performance
- Dashboard pages respond in < 300 ms (excluding SSH operations).
- Live log latency < 1 s between server output and browser.
- Rollback < 5 s.

### 9.3 Reliability
- Jobs are idempotent and retried only where safe (notifications yes, deploy steps no).
- One running deployment per project (atomic lock in Redis).
- A crashed worker leaves the deployment in `failed` with a clear message (stale-job detector).

### 9.4 Usability and accessibility
- Responsive (works on a phone to trigger a rollback).
- WCAG 2.1 AA: keyboard navigation, focus states, contrast, labelled forms.
- Light and dark themes.
- UI text in English, centralized for future translation.

### 9.5 Maintainability
- Strict TypeScript, PHPStan level 8 (Larastan), Laravel Pint, ESLint + Prettier.
- Test coverage ≥ 70% on the deployment domain logic.
- Architecture decisions recorded in `docs/adr/`.

## 10. Technical architecture

### 10.1 Stack

| Layer | Choice | Reason |
|---|---|---|
| Backend | **Laravel 12** (latest stable), PHP 8.3+ | Core expertise, queues, broadcasting, encryption built in |
| Frontend | **Vue 3 + TypeScript + Inertia.js** (official Laravel Vue starter kit), Tailwind CSS | SPA feel without a separate API for v1 |
| Real-time | **Laravel Reverb** (WebSockets) + Laravel Echo | First-party, self-hostable |
| Queue | **Redis** + **Laravel Horizon** | Reliable long-running deploy jobs, monitoring |
| Database | **PostgreSQL** (MySQL supported) | |
| SSH | **phpseclib 3** | Pure PHP, key generation, streaming output, no system `ssh` dependency |
| Tests | **Pest**, Vitest for Vue units | |
| Packaging | **Docker Compose** (app, worker, reverb, redis, postgres) | One-command self-hosting |

### 10.2 Backend structure (domain-oriented)

```
app/
├── Domain/
│   ├── Servers/        Models, Actions (CreateServer, TestConnection, CheckRequirements)
│   ├── Projects/       Models, Actions, EnvFileWriter
│   ├── Deployments/    Pipeline/, Steps/ (one class per step), Jobs/DeployJob, Events/
│   ├── Releases/       Actions (Rollback, Cleanup)
│   └── Notifications/  Channels (Discord, Slack, Mail)
├── Support/Ssh/        SshClient interface + PhpseclibSshClient + FakeSshClient (tests)
├── Http/Controllers/   Thin controllers (Inertia pages, webhook)
└── Policies/
```

Key design rules:
- Each pipeline step is a class implementing `DeploymentStep` (`name()`, `run(Context): void`),
  so the pipeline is testable and extensible (future recipes for other frameworks).
- `SshClient` is an interface; tests use a `FakeSshClient` that records commands, so the
  pipeline is unit-tested without a real server.

### 10.3 Data model (v1.0)

| Table | Main columns |
|---|---|
| `users` | id, name, email, password, two_factor_secret (encrypted), role |
| `servers` | id, name, host, port, username, private_key (encrypted), public_key, status, last_checked_at, requirements (json) |
| `projects` | id, server_id, name, slug, repository_url, branch, deploy_path, deploy_private_key (encrypted), deploy_public_key, webhook_secret (encrypted), env_content (encrypted), php_fpm_service, run_composer, run_npm_build, run_migrations, queue_restart, health_check_url, health_check_status, auto_rollback, keep_releases |
| `project_hooks` | id, project_id, stage (before_build/after_build/before_activate/after_activate), command, order |
| `deployments` | id, project_id, user_id (nullable), type (deploy/rollback), trigger (manual/webhook), status, commit_hash, commit_message, commit_author, release_name, started_at, finished_at, error_message |
| `deployment_logs` | id, deployment_id, step, line, stream (stdout/stderr), created_at |
| `notification_channels` | id, project_id, type (discord/slack/mail), target (encrypted), events (json), enabled |
| `activity_log` | id, user_id, subject_type, subject_id, action, properties (json), created_at |

### 10.4 Routes (main)

| Method | Path | Purpose |
|---|---|---|
| GET | `/` | Dashboard |
| GET/POST | `/servers`, `/servers/{id}` | Server CRUD, `POST /servers/{id}/test`, `/check` |
| GET/POST | `/projects`, `/projects/{id}` | Project CRUD, settings, env, hooks, notifications |
| POST | `/projects/{id}/deploy` | Manual deploy |
| POST | `/projects/{id}/releases/{release}/rollback` | Rollback |
| GET | `/deployments/{id}` | Deployment detail with live log |
| POST | `/webhooks/github/{project:slug}` | GitHub push webhook (HMAC verified) |

Broadcast channels: `private-deployments.{id}` (log lines, status), `private-projects` (dashboard feed).

### 10.5 UI screens

1. Setup (first run) · 2. Login · 3. Dashboard · 4. Servers list · 5. Server detail (key,
requirements, projects) · 6. Project detail (overview, releases, deployments) · 7. Project
settings (general, build, hooks, env, notifications, webhook) · 8. Deployment detail (step
timeline + live terminal-style log) · 9. Activity log · 10. Account settings (password, 2FA).

## 11. Development environment

Everything runs locally; no VPS is needed to build v1.0.

```
docker compose up
├── app        Laravel (PHP 8.3)
├── worker     Horizon queue worker
├── reverb     WebSocket server
├── redis
├── postgres
└── target     Ubuntu 24.04 + OpenSSH + PHP 8.3 + Composer + Node + Nginx
               (a fake VPS to deploy to, reachable as `target:22`)
```

A sample Laravel app repository is used as the deploy target during development.

## 12. Quality and CI

GitHub Actions on every push and pull request:
1. `composer validate`, `composer audit`
2. Laravel Pint (style), Larastan (static analysis, level 8)
3. Pest test suite (unit + feature, PostgreSQL + Redis services)
4. ESLint, Prettier check, `vue-tsc` type check, Vitest
5. Build the Docker image

An end-to-end test job deploys the sample app to the `target` container and asserts zero
downtime (curl loop) and a working rollback.

## 13. Roadmap

| Week | Milestone | Deliverables |
|---|---|---|
| 1 | Foundations | Laravel + Vue starter kit, Docker Compose with `target` server, CI pipeline, auth + first-run setup |
| 2 | Servers | Server CRUD, key generation, test connection, requirements check |
| 3 | Projects | Project CRUD, deploy key, shared paths, encrypted `.env` editor, initialize structure |
| 4 | Pipeline | Step classes, DeployJob, lock, timeouts, logs stored, tests with FakeSshClient |
| 5 | Real-time + webhook | Reverb live logs, deployment detail page, GitHub webhook with HMAC |
| 6 | Releases + notifications | Release list, rollback, cleanup, Discord/Slack/email, health check + auto-rollback |
| 7 | Polish + release v1.0 | Dashboard, activity log, accessibility pass, docs, install script, demo VPS, launch post |
| 8+ | v1.1 | Roles, monitoring, cron heartbeats, 2FA, CLI |

## 14. Deliverables

- Public GitHub repository (MIT) with README, screenshots/GIF, install guide, `CONTRIBUTING.md`
- Live demo instance on a VPS with a read-only demo account
- Documentation: installation, server preparation (user, sudoers line, Nginx example), FAQ
- Architecture decision records in `docs/adr/`
- A case study on the portfolio: problem, architecture, decisions, results

## 15. Success criteria

- A fresh user can install Pushpilot and complete a first deploy in **under 15 minutes**
  following the README.
- Zero failed requests during a deployment of the sample app (measured in CI).
- Rollback in under 5 seconds.
- CI green, coverage ≥ 70% on `app/Domain/Deployments`.

## 16. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Command injection through project settings | Escape every argument, validate inputs (paths, branch names, service names) with strict regex, tests for malicious inputs |
| Leaking secrets in logs | Masking filter on all streamed/stored output, tests |
| Long SSH operations blocking workers | Dedicated `deployments` queue with its own Horizon supervisor, timeouts |
| Migrations that cannot be rolled back | Clear warning in UI; rollback switches code only; documented |
| Scope creep | MoSCoW priorities; anything "C" waits for v1.1 |

## 17. Out of scope (v1.0)

Server provisioning, SSL/DNS management, database backups, Docker-based app deployment,
non-Laravel frameworks, multi-tenant SaaS, billing.
