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

## Documentation

- [Specification (cahier des charges)](docs/cahier-des-charges.md)

## License

[MIT](LICENSE) © Ghofrane Soulmi
