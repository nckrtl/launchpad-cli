# Launchpad

Local PHP development environment at ~/.config/launchpad/

## Commands

```bash
launchpad start         # Start all services
launchpad stop          # Stop all services
launchpad restart       # Restart everything
launchpad sites         # List all sites
launchpad php <site> <version>  # Set PHP version
```

## Direct Docker Access

```bash
# Start/stop individual services
docker compose -f ~/.config/launchpad/php/docker-compose.yml up -d
docker compose -f ~/.config/launchpad/postgres/docker-compose.yml down

# View logs
docker logs -f launchpad-php-83
docker logs -f launchpad-caddy
```

## Sites

Paths in config.json are served as {folder}.test (flat namespace, first match wins).

Set PHP version per project:

```bash
echo "8.1" > ~/Projects/mysite/.php-version
```

Or edit config.json:

```json
{ "sites": { "mysite": { "php_version": "8.1" } } }
```

Then restart: `launchpad restart`

## Add a New Path

1. Edit ~/.config/launchpad/config.json, add path to "paths" array
2. Edit ~/.config/launchpad/php/docker-compose.yml, add volume mount
3. Run: `launchpad restart`

## Config Locations

- PHP: ~/.config/launchpad/php/php.ini
- Caddy: ~/.config/launchpad/caddy/Caddyfile
- Sites: ~/.config/launchpad/config.json
- DNS: ~/.config/launchpad/dns/Dockerfile
