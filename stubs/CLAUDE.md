# Launchpad

Local PHP development environment at ~/.config/launchpad/

## Commands

```bash
launchpad start         # Start all services
launchpad stop          # Stop all services
launchpad restart       # Restart everything
launchpad status        # Check service status
launchpad sites         # List all sites
launchpad php <site> <version>  # Set PHP version
```

## Horizon (Queue Worker)

Launchpad includes a web app with Horizon for background job processing. Horizon runs in a Docker container (`launchpad-horizon`).

```bash
# Check Horizon status
launchpad horizon:status
docker ps | grep launchpad-horizon

# Start/Stop Horizon
launchpad horizon:start
launchpad horizon:stop
docker restart launchpad-horizon

# View logs
docker logs launchpad-horizon --tail 100 -f

# Access dashboard (when running)
open https://launchpad.{tld}/horizon
```

Docker configuration is at `~/.config/launchpad/horizon/docker-compose.yml`.

## Direct Docker Access

```bash
# Start/stop individual services
docker compose -f ~/.config/launchpad/php/docker-compose.yml up -d
docker compose -f ~/.config/launchpad/postgres/docker-compose.yml down

# View logs
docker logs -f launchpad-php-83
docker logs -f launchpad-caddy
docker logs -f launchpad-redis
docker logs -f launchpad-horizon
```

## Sites

Paths in config.json are served as {folder}.{tld} (flat namespace, first match wins).

Set PHP version per project:

```bash
echo "8.4" > ~/projects/mysite/.php-version
```

Or via CLI:

```bash
launchpad php mysite 8.4
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
- Horizon: ~/.config/launchpad/horizon/docker-compose.yml
- Web app: ~/.config/launchpad/web/

## Troubleshooting

```bash
# Check all services
launchpad status --json | jq .

# Check Horizon specifically
launchpad horizon:status
docker logs launchpad-horizon --tail 50

# Restart everything
launchpad restart

# Clear config cache in Horizon container
docker exec launchpad-horizon php artisan config:clear
docker restart launchpad-horizon
```
