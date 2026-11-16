# Kickoff prompt

Open Claude Code in this folder and paste the prompt below to start Milestone 1.
For later milestones, reuse the short prompt at the bottom.

---

## Prompt — Milestone 1 (Foundations)

```
You are working on Pushpilot, an open-source self-hosted deployment dashboard for Laravel apps.
Read CLAUDE.md and docs/cahier-des-charges.md fully before doing anything.

Goal: Milestone 1 (week 1) from section 13 of the spec — Foundations.

Deliverables:
1. Scaffold a new Laravel 12 app in this folder using the official Vue starter kit
   (Inertia + Vue 3 + TypeScript + Tailwind). Keep the existing docs/, CLAUDE.md, README.md, LICENSE.
2. Configure PostgreSQL and Redis; install Laravel Horizon, Laravel Reverb, phpseclib 3,
   Pest, Larastan (level 8) and Laravel Pint.
3. Docker Compose (section 11): app, worker (Horizon), reverb, redis, postgres, and a
   `target` service = Ubuntu 24.04 with OpenSSH, PHP 8.3-FPM, Composer, Node 20, Nginx,
   a `deployer` user, and the sudoers line allowing only `systemctl reload php8.3-fpm`.
   Add a Makefile or composer scripts: `up`, `down`, `test`, `lint`.
4. Authentication: keep the starter kit login, remove public registration, add the
   first-run setup screen (AUTH-2) that creates the owner account only when no user exists.
5. Create the folder skeleton from section 10.2 (app/Domain/*, app/Support/Ssh) with the
   `SshClient` interface, an empty `PhpseclibSshClient` and a `FakeSshClient` for tests.
6. GitHub Actions CI (section 12): pint, larastan, pest (with postgres + redis services),
   eslint, prettier, vue-tsc, build.
7. Update README.md with local setup instructions.

Rules:
- First show me your plan (files, packages, commands) and wait for my approval.
- Use the latest stable versions of every package.
- Run all quality gates from CLAUDE.md before saying you are done, and show me the results.
- Commit in small Conventional Commits, without any AI attribution or Co-Authored-By lines.
- If something in the spec is ambiguous, ask me instead of guessing.
```

---

## Prompt — next milestones

```
Read CLAUDE.md and docs/cahier-des-charges.md. Milestone <N> from section 13 is next:
<copy the milestone line>. Show me your plan with the requirement IDs you will cover
(e.g. SRV-1..SRV-5) and the tests you will write, then wait for my approval.
```
