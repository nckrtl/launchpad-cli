# Launchpad CLI

A local PHP development environment powered by Docker. Launchpad provides a simple, fast way to run PHP applications locally with automatic HTTPS, multiple PHP versions, and essential services.

## Features

- **Multiple PHP Versions** - Run PHP 8.3, 8.4, and 8.5 side-by-side via PHP-FPM
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
| `launchpad php <site> <version>` | Set PHP version for a site (8.3, 8.4, 8.5) |
| `launchpad logs` | Tail container logs |
| `launchpad trust` | Install Caddy root CA for local HTTPS |
| `launchpad upgrade` | Upgrade to the latest version |
| `launchpad rebuild` | Rebuild PHP images with Redis and other extensions |
| `launchpad upgrade --check` | Check for available updates |
| `launchpad worktrees` | List all git worktrees |
| `launchpad worktree:refresh` | Auto-detect and link new worktrees |
| `launchpad worktree:unlink <site> <wt>` | Remove worktree routing |
| `launchpad project:create <name>` | Create project with async provisioning |
| `launchpad project:list` | List all projects in scan paths |
| `launchpad project:scan` | Scan for git repositories |
| `launchpad project:update [path]` | Update project (git pull + deps) |
| `launchpad project:delete <slug>` | Delete project with cascade |
| `launchpad provision <slug>` | Background provisioning (internal) |
| `launchpad provision:status <slug>` | Check provisioning status |
| `launchpad reverb:setup` | Setup Reverb WebSocket service |

## Services & Ports

## Service Management

Launchpad provides a declarative service management system. Services are defined as templates and can be enabled, configured, or disabled.

### Service Commands

| Command | Description |
|---------|-------------|
| `launchpad service:list` | List configured services with status |
| `launchpad service:list --available` | Show available service templates |
| `launchpad service:enable <name>` | Enable a service with defaults |
| `launchpad service:disable <name>` | Disable a service |
| `launchpad service:configure <name> --set key=value` | Update service configuration |
| `launchpad service:info <name>` | Show detailed service information |

### Available Services

| Service | Category | Default Port | Description |
|---------|----------|--------------|-------------|
| postgres | database | 5432 | PostgreSQL database |
| mysql | database | 3306 | MySQL database |
| redis | cache | 6379 | Redis cache/session store |
| mailpit | mail | 1025/8025 | Email testing (SMTP/Web UI) |
| meilisearch | search | 7700 | Full-text search engine |
| reverb | websocket | 6001 | Laravel Reverb WebSocket |
| dns | core | 53 | Local DNS resolver |

### Examples

```bash
# List current services
launchpad service:list

# Enable MySQL database
launchpad service:enable mysql

# Change PostgreSQL to version 16
launchpad service:configure postgres --set version=16

# Change Redis max memory
launchpad service:configure redis --set maxmemory=512mb

# Show service details
launchpad service:info postgres
```

### Configuration

Services are configured in `~/.config/launchpad/services.yaml`. Each service can specify:

- `enabled` - Whether the service is active
- `version` - Service version (from available versions)
- `port` - Port mapping
- `environment` - Environment variables
- Additional service-specific options

Changes to services.yaml automatically regenerate `docker-compose.yaml`.

| Service | Port(s) | Description |
|---------|---------|-------------|
| Caddy | 80, 443 | Web server with automatic HTTPS |
| PHP 8.3 | - | PHP-FPM pool |
| PHP 8.4 | - | PHP-FPM pool |
| PHP 8.5 | - | PHP-FPM pool |
| PostgreSQL | 5432 | Database server |
| Redis | 6379 | Cache server |
| Mailpit | 1025, 8025 | Mail catcher (SMTP: 1025, Web UI: 8025) |
| DNS | 53 | Local DNS resolver for .test domains |

## Configuration

Launchpad stores its configuration at `~/.config/launchpad/config.json`. You can customize:

### Paths

Add directories to scan for projects:

```json
{
  "paths": ["~/projects", "~/clients"]
}
```

### Custom Site Paths

Override the auto-detected path for any site. Useful for nested projects:

```json
{
  "sites": {
    "mysite": {
      "path": "~/projects/monorepo/apps/mysite"
    }
  }
}
```

### Default PHP Version

```json
{
  "default_php_version": "8.4"
}
```

### TLD

Change the top-level domain (default: `test`):

```json
{
  "tld": "local"
}
```


## Git Worktree Support

Launchpad automatically detects git worktrees and creates subdomains:

```bash
# Create a worktree for your project
cd ~/projects/myapp
git worktree add ../myapp-feature-auth feature/auth

# Refresh to pick up the new worktree
launchpad worktree:refresh

# Access via subdomain
# https://feature-auth.myapp.test
```

Worktrees are served from `<worktree-name>.<site>.test`.

## Requirements

### Required

| Dependency | macOS | Linux |
|------------|-------|-------|
| PHP >= 8.2 | `php.new` or Homebrew | `php.new` or apt |
| Docker | OrbStack (recommended) or Docker Desktop | docker.io |
| Composer | Homebrew | apt |
| Supervisor | Homebrew | apt (for Horizon queue worker) |

### Optional

| Dependency | Purpose |
|------------|---------|
| dig | DNS debugging (built-in on macOS, `apt install dnsutils` on Linux) |

**Note:** The `launchpad init` command will check for and automatically install missing prerequisites.

## Development

### Setup

```bash
git clone https://github.com/nckrtl/launchpad-cli.git
cd launchpad-cli
composer install

# Enable git hooks
git config core.hooksPath .githooks
```

### Quality Tools

| Tool | Command | Description |
|------|---------|-------------|
| PHPStan | `./vendor/bin/phpstan analyse` | Static analysis (level 5) |
| Rector | `./vendor/bin/rector` | Automated refactoring |
| Pint | `./vendor/bin/pint` | Code formatting |
| Pest | `./vendor/bin/pest` | Test suite |

### Running Checks

```bash
# Run all checks (same as pre-commit hook)
./vendor/bin/rector --dry-run
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=512M
./vendor/bin/pest
```

### Pre-commit Hook

The project includes a pre-commit hook that runs all quality checks before each commit. Enable it with:

```bash
git config core.hooksPath .githooks
```

### CI

GitHub Actions runs the full quality check suite on every push and PR to main.

## License

MIT License
