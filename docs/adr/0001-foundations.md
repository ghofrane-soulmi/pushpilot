# ADR 0001 — Foundations: starter kit, tooling and dev environment

- **Status:** accepted
- **Date:** 2026-11-16
- **Milestone:** 1 (Foundations)

## Context

The specification fixes the stack (Laravel 12, Vue 3 + TypeScript + Inertia, Reverb, Redis +
Horizon, PostgreSQL, phpseclib 3, Pest, Docker Compose) and asks for the official Laravel Vue
starter kit. When the project started, Laravel 13 was the latest release and the current Vue
starter kit (Inertia 3, Fortify, Wayfinder, Vite+) targets it; the only Laravel 12 tag of the
kit was an outdated early-2025 release.

## Decisions

### 1. Laravel 12 with the current Vue starter kit

We use the current starter kit and pin `laravel/framework` to `^12.0`. Every kit dependency
resolves on Laravel 12. Two Laravel 13-only APIs used by the kit were replaced:

- `Password::toPasswordRulesString()` → `App\Support\PasswordRulesAttribute` (unit tested).
- `#[Fillable]` / `#[Hidden]` model attributes → classic `$fillable` / `$hidden` properties.

Upgrading to Laravel 13 later means deleting `PasswordRulesAttribute` and bumping the constraint.

### 2. Kit features kept and removed

| Feature | Decision | Reason |
|---|---|---|
| Registration | removed | Replaced by the first-run setup screen (AUTH-2) |
| Email verification | removed | Single owner, no mail flow in v1.0 |
| Passkeys | removed | Not in the specification |
| Two-factor authentication | kept | AUTH-3 |
| Password confirmation | kept | Protects the 2FA settings |
| Password reset | removed | Its pages are public; the spec allows only login, setup and the webhook unauthenticated |
| Account deletion | removed | Deleting the only owner would reopen `/setup` to anyone, handing over every server key |

### 3. First-run setup

`/setup` is the only way to create a user. Every web request is redirected there while the
`users` table is empty (the middleware runs before `auth`), and it returns 404 afterwards.
Creation is serialised with a cache lock so concurrent submissions cannot create two owners.
A route-audit test fails if any new route is reachable without authentication.

### 4. Frontend tooling: Vite+ instead of ESLint + Prettier

The kit ships Vite+ (`vp check`: Oxlint + Oxfmt), which covers linting and formatting.
`npm run lint` and `npm run typecheck` map to `vp check` and `vue-tsc` so the quality gates in
`CLAUDE.md` work unchanged. Markdown files are excluded from formatting so documents keep
their authored layout.

### 5. Development environment

- One PHP 8.4 image serves `app` (`artisan serve`), `worker` (Horizon) and `reverb`. Vite runs
  on the host. Containers read `.env` from the mounted project, so `phpunit.xml` test settings
  apply both locally and in Docker.
- PostgreSQL 18 and Redis 8 use the official Alpine images.
- `target` is an Ubuntu 24.04 "fake VPS" (OpenSSH key-only, PHP 8.3-FPM, Composer, Node 20,
  Nginx, `deployer` user). Containers have no systemd, so `/usr/bin/systemctl` is a shim that
  implements only `reload php8.3-fpm`; the sudoers line is therefore identical to production.
- Horizon requires `ext-pcntl`/`ext-posix`, unavailable on Windows. They are declared under
  `config.platform` in `composer.json` so Composer installs on Windows; Horizon only runs in Linux.

### 6. SSH abstraction

All SSH goes through `App\Support\Ssh\SshClient` (`connect`, `run` with streamed
stdout/stderr, `disconnect`). `SshCredentials` redacts the private key from dumps and traces.
`FakeSshClient` records commands and returns scripted results so pipeline steps are unit-tested
without a server. `PhpseclibSshClient` is implemented in Milestone 2.

## Consequences

- The codebase can move to Laravel 13 with a small, known diff.
- Password recovery for the owner is a CLI task until email support exists (v1.0 "should").
- Some spec items (Vitest, Docker image build for production, end-to-end deploy test) land in
  later milestones once there is something to test.
