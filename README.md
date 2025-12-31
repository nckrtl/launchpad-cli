# Launchpad CLI

A local PHP development environment powered by Docker. Launchpad provides a simple, fast way to run PHP applications locally with automatic HTTPS, multiple PHP versions, and essential services.

## Features

- **Multiple PHP Versions** - Run PHP 8.3 and 8.4 side-by-side via FrankenPHP
- **Automatic HTTPS** - Local SSL certificates via Caddy
- **Essential Services** - PostgreSQL, Redis, and Mailpit included
- **Simple DNS** - Automatic `.test` domain resolution
- **Per-site PHP** - Configure PHP version per project

## Installation

Download the latest release:

```bash
curl -L -o ~/.local/bin/launchpad https://github.com/nckrtl/launchpad-cli/releases/latest/download/launchpad.phar
chmod +x ~/.local/bin/launchpad
```

Make sure `~/.local/bin` is in your PATH.

## Quick Start

1. Initialize Launchpad (first time only):
   ```bash
   launchpad init
   ```

2. Start the services:
   ```bash
   launchpad start
   ```

3. Trust the local CA certificate (for HTTPS):
   ```bash
   launchpad trust
   ```

4. Link your project (creates a symlink in ~/projects):
   ```bash
   ln -s /path/to/your/project ~/projects/myapp
   ```

5. Visit https://myapp.test in your browser!

## Commands

| Command | Description |
|---------|-------------|
| `launchpad init` | First-time setup: creates config, pulls images, sets up DNS |
| `launchpad start` | Start all Launchpad services |
| `launchpad stop` | Stop all Launchpad services |
| `launchpad restart` | Restart all Launchpad services |
| `launchpad status` | Show status and running services |
| `launchpad sites` | List all sites with their PHP versions |
| `launchpad php <site> <version>` | Set PHP version for a site (8.3 or 8.4) |
| `launchpad logs` | Tail container logs |
| `launchpad trust` | Install Caddy root CA for local HTTPS |

## Services & Ports

| Service | Port(s) | Description |
|---------|---------|-------------|
| Caddy | 80, 443 | Web server with automatic HTTPS |
| PHP 8.3 | - | FrankenPHP worker |
| PHP 8.4 | - | FrankenPHP worker |
| PostgreSQL | 5432 | Database server |
| Redis | 6379 | Cache server |
| Mailpit | 1025, 8025 | Mail catcher (SMTP: 1025, Web UI: 8025) |
| DNS | 53 | Local DNS resolver for .test domains |

## Requirements

- Docker
- macOS or Linux

## License

MIT License
