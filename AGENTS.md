# Agent Instructions

This project uses **bd** (beads) for issue tracking. Run `bd onboard` to get started.

## Project Overview

**Orbit CLI** - Local PHP dev environment powered by Docker. Supports both Linux and macOS.

## Bundled Web App

The `web/` directory contains a Laravel web app that is bundled with the CLI. This is the dashboard/API that powers the desktop app integration.

- Source: `web/` in this repo
- Installed to: `~/.config/orbit/web/` on user machines
- The CLI copies `web/` during init/upgrade

When making changes to the web app, edit files in `web/` - NOT in `~/.config/orbit/web/` (that's the installed copy).

## Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --status in_progress  # Claim work
bd close <id>         # Complete work
bd sync               # Sync with git
```

## Quality Gates

**IMPORTANT:** Every fix must have a test. After tests pass, release immediately.

Run before every commit/release:

```bash
./vendor/bin/rector --dry-run
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=512M
./vendor/bin/pest
```

## Local Development Setup

Dev machine uses symlink (not downloaded PHAR):

```bash
ln -s /home/nckrtl/projects/orbit-cli/orbit ~/.local/bin/orbit
```

Ensure `~/.local/bin` is in PATH (already configured in .bashrc).

## After Making Changes

**IMPORTANT: Always complete the full workflow:**

1. **Test locally**: `./vendor/bin/pest` (if code changed)
2. **Add tests**: Every feature must have Pest tests
3. **Commit changes**: Use descriptive commit message
4. **Push via gh CLI**: `git push`

## Release Workflow

After fixes verified (all tests pass):

1. Bump version if needed
2. Create tag: `git tag v0.x.y`
3. Push tag: `git push origin v0.x.y`
4. GitHub Actions builds PHAR and creates release
5. Update local: `orbit upgrade` (or pull latest for symlink setup)

## Platform Support

Code must work on both Linux and macOS. Use `PlatformService` for OS detection:
- `$platform->isMac()` / `$platform->isLinux()`
- Platform-specific adapters in `app/Services/Platform/`

## Known Gotchas

### Bun/Node Package Managers in Background Processes

**Problem:** `bun install` (and potentially other package managers) can hang indefinitely when executed from PHP in background/non-interactive contexts like Laravel Horizon queue workers or launchd services.

**Root Cause:** Package managers often try to display progress bars or interactive output. When there's no TTY (terminal) available, the process can block waiting for terminal operations that will never complete.

**Solution:** Always use CI-mode commands when running package managers from PHP:

```php
// BAD - can hang in background processes
Process::run('bun install');
Process::run('bun install --no-progress');

// GOOD - designed for non-interactive environments
Process::run('bun ci');

// Also set CI environment variable for extra safety
Process::env(['CI' => '1'])->run('bun ci');
```

**Key Points:**
- `bun ci` is specifically designed for CI/non-TTY environments
- Always set `CI=1` environment variable when running from PHP background processes
- This applies to Horizon jobs, queue workers, launchd services, and any PHP subprocess without a TTY
- The issue does NOT occur when running PHP scripts interactively from terminal
- `shell_exec()` and Laravel's `Process::run()` both work fine - the issue is TTY detection in bun
- npm has similar issues; use `npm ci` instead of `npm install` in CI contexts

**Debugging Tips:**
- If bun hangs, test the same command directly in terminal (it will work)
- Check if process is running in Horizon vs direct CLI invocation
- Increase timeout and check logs for partial output
- Use `Process::tty()` if you need interactive mode (but avoid in background jobs)

### Platform-Specific Service Commands

**Problem:** Service management commands differ between Linux and macOS.

**Solution:** Always use `PlatformAdapter` for service operations:

```php
// BAD - Linux only
Process::run('sudo systemctl reload caddy');

// GOOD - cross-platform
$this->phpManager->getAdapter()->reloadCaddy();
```

**macOS uses:**
- `brew services restart caddy`
- `brew services restart php@8.4`

**Linux uses:**
- `sudo systemctl reload caddy`
- `sudo systemctl restart php8.4-fpm`

### PHP-FPM Pool Configuration

PHP-FPM pool configs are stored in different locations per OS:
- **macOS:** `/opt/homebrew/etc/php/{version}/php-fpm.d/orbit-{version}.conf`
- **Linux:** `/etc/php/{version}/fpm/pool.d/orbit-{version}.conf`

When regenerating configs, ensure:
- Pool names use "orbit-XX" format (not legacy "launchpad-XX")
- Socket paths point to `~/.config/orbit/php/phpXX.sock`
- Log paths point to `~/.config/orbit/logs/phpXX-fpm.log`
- Environment variables include PATH with `~/.bun/bin` for bun access

## Landing the Plane (Session Completion)

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd sync
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
