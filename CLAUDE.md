# Pushpilot — instructions for Claude Code

Read `docs/cahier-des-charges.md` before any work: it is the source of truth for scope,
architecture, data model and priorities.

## Git
- Never add `Co-Authored-By`, "Generated with Claude Code" or any AI attribution to commits or PRs.
- Small commits in Conventional Commits style (`feat:`, `fix:`, `chore:`, `test:`, `docs:`).
- Commit only when asked or at the end of a milestone step the user approved.

## Stack
Laravel 12 (PHP 8.3), Vue 3 + TypeScript (strict) + Inertia, Tailwind CSS, Laravel Reverb,
Redis + Horizon, PostgreSQL, phpseclib 3, Pest, Docker Compose.

## Architecture rules
- Domain code lives in `app/Domain/<Context>/` (Servers, Projects, Deployments, Releases, Notifications).
- Controllers stay thin: validate (Form Requests), authorize (Policies), call an Action, return Inertia/JSON.
- Each deployment step is its own class implementing `DeploymentStep`.
- All SSH goes through the `App\Support\Ssh\SshClient` interface; tests use `FakeSshClient`.
- Vue: `<script setup lang="ts">`, typed props, pages in `resources/js/pages`, shared UI in `resources/js/components`.

## Security rules (non-negotiable)
- Every value inserted in a shell command goes through `escapeshellarg` or a validated allowlist.
- Secrets (keys, webhook secrets, `.env`) use encrypted casts and are never logged or sent to the browser after creation.
- Mask secret values in deployment output.
- Webhooks are verified with HMAC SHA-256 before anything else.

## Quality gates (run before saying a task is done)
- `./vendor/bin/pint --test`
- `./vendor/bin/phpstan analyse`
- `php artisan test`
- `npm run lint && npm run typecheck && npm run build`

## Working style
- Follow the roadmap in the spec (section 13), one milestone at a time.
- Before a milestone, show a short plan and wait for approval.
- Write tests alongside features (Pest feature tests for HTTP, unit tests for pipeline steps).
- Do not invent features outside the spec; propose them instead.
